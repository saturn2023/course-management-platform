<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives one uploaded enrolment document. Port of ams2_upload().
 *
 * One file per request, deliberately: it is what lets a single flaky upload be
 * retried on its own without resubmitting the enrolment, and it keeps each
 * request well inside `upload_max_filesize` instead of needing the sum of every
 * document to fit.
 *
 * The type is decided from the file's CONTENTS, never from the name or the
 * browser's Content-Type, so a renamed .docx cannot pass as a PDF. The stored
 * name is randomised while `original_name` keeps what the learner called it,
 * because RTO Data must receive the original — the whole point of the 19/10
 * fix.
 */
class Ams2DocumentReceiver
{
    public const DISK = Ams2TestDocuments::DISK;

    public const DIRECTORY = Ams2TestDocuments::DIRECTORY;

    /**
     * How long after submitting a learner can still upload. Past this the row
     * has long since been processed and a late file would never be sent.
     */
    public const UPLOAD_WINDOW_DAYS = 7;

    /**
     * Accepted types, mapped to the extension the file is stored under. Taken
     * from the detected MIME, not the uploaded filename.
     */
    public const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private Ams2DocumentLimit $limit) {}

    /**
     * The row this upload belongs to.
     *
     * Matched on the id AND the secret key together, so knowing a row id is
     * not enough to attach a document to someone else's enrolment.
     */
    public function rowFor(int $id, string $secretKey): Ams2Enrolment
    {
        $row = Ams2Enrolment::query()
            ->where('id', $id)
            ->where('secret_key', $secretKey)
            ->first();

        if (! $row) {
            throw new Ams2DocumentRejected(
                'We could not match this document to your enrolment. Email it to sales@amstraining.com.au instead.',
                'no_match',
                403,
            );
        }

        if ($row->created_at && $row->created_at->lessThan(now()->subDays(self::UPLOAD_WINDOW_DAYS))) {
            throw new Ams2DocumentRejected(
                'Online uploads for this enrolment have closed. Email the document to sales@amstraining.com.au.',
                'closed',
                403,
            );
        }

        return $row;
    }

    public function assertKnownType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        if (! in_array($type, Ams2DocumentTypes::keys(), true)) {
            throw new Ams2DocumentRejected('Unknown document type.', 'bad_type');
        }

        return $type;
    }

    /**
     * Store the file and record it against the row.
     */
    public function receive(Ams2Enrolment $row, string $type, ?UploadedFile $file): Ams2EnrolmentDocument
    {
        $file = $this->validate($file);
        $mime = $this->detectMime($file);

        $path = self::DIRECTORY.'/'.$row->id.'/'
            .Str::lower((string) Str::ulid()).'.'.self::ALLOWED[$mime];

        $stored = Storage::disk(self::DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        if ($stored === false) {
            throw new Ams2DocumentRejected(
                'The server could not store this file. Try again later.',
                'store_failed',
                500,
            );
        }

        $this->replacePrevious($row, $type);

        $document = Ams2EnrolmentDocument::create([
            'enrolment_row' => $row->id,
            'doc_type' => $type,
            'file_path' => $path,
            'original_name' => $this->safeName($file->getClientOriginalName()),
            'mime' => $mime,
            'size_bytes' => $file->getSize(),
            'status' => Ams2EnrolmentDocument::STATUS_PENDING,
            'attempts' => 0,
        ]);

        Log::info('[AMS2] document_stored', [
            'reference' => $row->displayRef(),
            'doc_type' => $type,
            'file_name' => $document->original_name,
            'file_size' => $document->size_bytes,
            'mime' => $mime,
        ]);

        return $document;
    }

    /**
     * A retry supersedes any unsent file of the same type.
     *
     * Only unsent ones: a document already sent to RTO Data is left alone, and
     * its file has been deleted by the sender anyway.
     */
    private function replacePrevious(Ams2Enrolment $row, string $type): void
    {
        $previous = Ams2EnrolmentDocument::query()
            ->where('enrolment_row', $row->id)
            ->where('doc_type', $type)
            ->whereIn('status', [
                Ams2EnrolmentDocument::STATUS_PENDING,
                Ams2EnrolmentDocument::STATUS_RETRY,
                Ams2EnrolmentDocument::STATUS_FAILED,
            ])
            ->get();

        foreach ($previous as $document) {
            Storage::disk(self::DISK)->delete($document->file_path);

            $document->update(['status' => Ams2EnrolmentDocument::STATUS_REPLACED]);
        }
    }

    private function validate(?UploadedFile $file): UploadedFile
    {
        if (! $file) {
            throw new Ams2DocumentRejected('No file was received. Try again.', 'no_file');
        }

        if (! $file->isValid()) {
            throw new Ams2DocumentRejected(
                $this->uploadErrorMessage($file->getError()),
                'upload_error_'.$file->getError(),
            );
        }

        if ($file->getSize() === 0) {
            throw new Ams2DocumentRejected(
                'This file is empty. Please choose the file again or save a new copy first.',
                'empty',
            );
        }

        if ($file->getSize() > $this->limit->bytes()) {
            throw new Ams2DocumentRejected('This file is too large.', 'too_large');
        }

        return $file;
    }

    private function detectMime(UploadedFile $file): string
    {
        // Read from the file itself. getClientMimeType() is whatever the
        // browser claimed and can be anything.
        $mime = (string) $file->getMimeType();

        if (! isset(self::ALLOWED[$mime])) {
            throw new Ams2DocumentRejected(
                'This file type is not accepted. Upload a PDF, JPG, PNG or WEBP file.',
                'bad_mime',
            );
        }

        return $mime;
    }

    /**
     * PHP's own upload failures, in words a learner can act on.
     */
    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'This file is too large.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Check your connection and try again.',
            UPLOAD_ERR_NO_FILE => 'No file was received. Try again.',
            default => 'The server could not receive this file. Try again.',
        };
    }

    /**
     * Equivalent of sanitize_file_name(), with one addition: this value is sent
     * to RTO Data as the document's filename, so it must not carry a path.
     */
    private function safeName(?string $name): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = preg_replace('/[\x00-\x1F\x7F"\'<>|?*:]+/u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return $name === '' ? 'document' : Str::limit($name, 200, '');
    }
}

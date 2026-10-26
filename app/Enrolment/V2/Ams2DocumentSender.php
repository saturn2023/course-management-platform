<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sends an enrolment's outstanding documents to RTO Data.
 *
 * Port of ams2_send_documents(). Only `pending` and `retry` documents are ever
 * selected, which is what makes a retry safe: a document already marked `sent`
 * cannot be picked up again, so it cannot be uploaded twice.
 */
class Ams2DocumentSender
{
    /**
     * AMS2_MAX_ATTEMPTS. Shared with Ams2Failer so a document and a step give
     * up after the same number of tries.
     */
    public const MAX_ATTEMPTS = Ams2Failer::MAX_ATTEMPTS;

    public function __construct(
        private Ams2DocumentUploader $uploader,
    ) {}

    /**
     * @return array{pending: int, failed: array<string, string>}
     */
    public function send(Ams2Enrolment $row): array
    {
        $failed = [];

        $documents = Ams2EnrolmentDocument::query()
            ->where('enrolment_row', $row->id)
            ->whereIn('status', [
                Ams2EnrolmentDocument::STATUS_PENDING,
                Ams2EnrolmentDocument::STATUS_RETRY,
            ])
            ->orderBy('id')
            ->get();

        foreach ($documents as $document) {
            if (! $this->claim($document)) {
                // Another run took it.
                continue;
            }

            $this->sendOne($row, $document, $failed);
        }

        return [
            'pending' => $this->outstandingCount($row),
            'failed' => $failed,
        ];
    }

    /**
     * @param  array<string, string>  $failed
     */
    private function sendOne(Ams2Enrolment $row, Ams2EnrolmentDocument $document, array &$failed): void
    {
        $result = $this->uploader->upload((string) $row->enrolment_id, $document);

        if ($result->fileMissing) {
            $document->update([
                'status' => Ams2EnrolmentDocument::STATUS_FAILED,
                'error_message' => 'File missing on server',
            ]);

            $failed[$document->doc_type] = 'The file is missing from the website server.';

            Log::error('[AMS2] document_file_missing', [
                'reference' => $row->reference(),
                'doc_type' => $document->doc_type,
                'file' => basename($document->file_path),
            ]);

            return;
        }

        if ($result->succeeded) {
            /*
             * The local copy is deleted once RTO Data holds it, as the
             * reference does. A failed document keeps its file so it can be
             * retried or recovered by hand.
             */
            Storage::disk(Ams2TestDocuments::DISK)->delete($document->file_path);

            $document->update([
                'status' => Ams2EnrolmentDocument::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ]);

            Log::info('[AMS2] document_sent', [
                'reference' => $row->reference(),
                'doc_type' => $document->doc_type,
                'name' => $document->original_name,
            ]);

            return;
        }

        $attempts = (int) $document->attempts + 1;
        $final = $attempts >= self::MAX_ATTEMPTS;

        $document->update([
            'status' => $final
                ? Ams2EnrolmentDocument::STATUS_FAILED
                : Ams2EnrolmentDocument::STATUS_RETRY,
            'attempts' => $attempts,
            'error_message' => json_encode($result->toArray()),
        ]);

        Log::log($final ? 'error' : 'warning', '[AMS2] document_send_failed', [
            'reference' => $row->reference(),
            'doc_type' => $document->doc_type,
            'attempt' => $attempts.' of '.self::MAX_ATTEMPTS,
            'http_status' => $result->httpStatus,
            'error' => $result->error,
            'raw_response' => mb_substr($result->rawResponse, 0, 1000),
        ]);

        if ($final) {
            $failed[$document->doc_type] = 'RTO Data rejected it '.$attempts
                .' times. The file is still on the website server: '
                .basename($document->file_path);
        }
    }

    /**
     * Atomic claim, same rule as a row transition: only the run that changes
     * the status gets to send the file.
     */
    private function claim(Ams2EnrolmentDocument $document): bool
    {
        $affected = DB::table('ams2_enrolment_documents')
            ->where('id', $document->id)
            ->where('status', $document->status)
            ->update([
                'status' => Ams2EnrolmentDocument::STATUS_SENDING,
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            return false;
        }

        $document->refresh();

        return true;
    }

    /**
     * Documents not yet finished one way or the other.
     */
    private function outstandingCount(Ams2Enrolment $row): int
    {
        return Ams2EnrolmentDocument::query()
            ->where('enrolment_row', $row->id)
            ->whereIn('status', [
                Ams2EnrolmentDocument::STATUS_PENDING,
                Ams2EnrolmentDocument::STATUS_RETRY,
                Ams2EnrolmentDocument::STATUS_SENDING,
            ])
            ->count();
    }
}

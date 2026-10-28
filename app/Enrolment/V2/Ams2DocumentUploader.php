<?php

namespace App\Enrolment\V2;

use App\Models\Ams2EnrolmentDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sends one document to RTO Data. Port of rtodata_upload_document().
 *
 * The file goes up base64-encoded inside the JSON body as a data: URI, and the
 * `name` field is what RTO Data files it under.
 *
 * THE 19/10 FIX: `name` must be the ORIGINAL uploaded filename, never the
 * randomised name the file is stored under. A learner's document arriving in
 * RTO Data as "01kwxetpy1.pdf" is useless to whoever has to read it.
 */
class Ams2DocumentUploader
{
    /**
     * Exactly the test rtodata_upload_document() applies: a substring search of
     * the whole raw response. Not a JSON decode — reproduced as-is, because a
     * stricter test could accept a response WordPress rejects, or vice versa.
     */
    public const SUCCESS_MARKER = '"success":1';

    public function __construct(
        private Ams2ApiClient $api,
    ) {}

    public function upload(string $enrolmentId, Ams2EnrolmentDocument $document): Ams2UploadResult
    {
        $disk = Storage::disk(Ams2TestDocuments::DISK);

        if (! $disk->exists($document->file_path)) {
            return Ams2UploadResult::fileMissing();
        }

        $contents = (string) $disk->get($document->file_path);
        $mime = $document->mime ?: $disk->mimeType($document->file_path) ?: 'application/octet-stream';

        $result = $this->api->post(Ams2ApiClient::RECEIVE, [
            'type' => 'receive',
            'enrolment_id' => (string) $enrolmentId,
            'files' => [[
                'name' => $this->displayName($document),
                'uri' => 'data:'.$mime.';base64,'.base64_encode($contents),
            ]],
        ]);

        $succeeded = str_contains($result->rawFull, self::SUCCESS_MARKER);

        $this->warnOnSuccessMismatch($document, $result, $succeeded);

        return new Ams2UploadResult(
            succeeded: $succeeded,
            httpStatus: $result->http,
            error: $result->error,
            rawResponse: $result->raw,
        );
    }

    /**
     * The name RTO Data files the document under.
     *
     * Falls back to the stored filename only when nothing was captured at
     * upload time, matching the reference.
     */
    private function displayName(Ams2EnrolmentDocument $document): string
    {
        $name = trim((string) $document->original_name);

        return $name !== '' ? $name : basename($document->file_path);
    }

    /**
     * The substring test is brittle: it wants `"success":1` with no space and
     * no other spelling. If RTO Data ever answers `"success": 1` or
     * `"success":true`, this flags it rather than letting a working upload be
     * recorded as a failure for five attempts with nobody knowing why.
     *
     * Deliberately does not change the outcome.
     */
    private function warnOnSuccessMismatch(
        Ams2EnrolmentDocument $document,
        Ams2ApiResult $result,
        bool $succeeded,
    ): void {
        if ($succeeded) {
            return;
        }

        $decoded = json_decode($result->rawFull, true);

        if (! is_array($decoded) || empty($decoded['success'])) {
            return;
        }

        Log::warning('[AMS2] ams2_document_success_mismatch', [
            'document_id' => $document->id,
            'enrolment_row_id' => $document->enrolment_row,
            'doc_type' => $document->doc_type,
            'reason' => 'Body decodes with a truthy success value, but the "'
                .self::SUCCESS_MARKER.'" substring is absent.',
            'http_status' => $result->http,
            'raw_response' => $result->raw,
        ]);
    }
}

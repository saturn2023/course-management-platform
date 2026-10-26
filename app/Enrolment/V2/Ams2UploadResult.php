<?php

namespace App\Enrolment\V2;

/**
 * The outcome of sending one document to RTO Data.
 *
 * Mirrors the array rtodata_upload_document() returns: an http status, a
 * transport error, and the raw body — all of which are written to the
 * document's error_message when a send fails, because that is what someone
 * diagnosing it later has to work from.
 */
class Ams2UploadResult
{
    public function __construct(
        public bool $succeeded,
        public int $httpStatus,
        public string $error,
        public string $rawResponse,
        public bool $fileMissing = false,
    ) {}

    public static function fileMissing(): self
    {
        return new self(
            succeeded: false,
            httpStatus: 0,
            error: 'File not found',
            rawResponse: '',
            fileMissing: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'http_status' => $this->httpStatus,
            'curl_error' => $this->error !== '' ? $this->error : 'none',
            'raw_response' => $this->rawResponse,
        ];
    }
}

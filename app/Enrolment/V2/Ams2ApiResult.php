<?php

namespace App\Enrolment\V2;

/**
 * One RTO Data v2 response, in the shape ams2_api() returns.
 *
 * The distinction that matters is between "RTO Data said no" and "we do not
 * know what RTO Data did". The second can mean the enrolment was created
 * anyway, so it must never be retried blindly — see unclear().
 */
class Ams2ApiResult
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        public bool $sent,
        public string $error,
        public int $http,
        public ?array $body,
        /** Truncated to 2000 chars, as ams2_api() does, for log lines and messages. */
        public string $raw,
        /**
         * The untruncated body.
         *
         * rtodata_upload_document() decides success by searching the WHOLE
         * response for a substring, so truncating first could turn a success
         * into a failure on a verbose response.
         */
        public string $rawFull = '',
    ) {}

    public function succeeded(): bool
    {
        return ! empty($this->body['success']);
    }

    /**
     * No clear answer: the request may still have been carried out.
     *
     * Port of the branch at ams2_step_enrol(): transport failure, a 5xx, a body
     * that is not JSON, or a success flag with nothing usable attached.
     */
    public function unclear(): bool
    {
        return ! $this->sent
            || $this->http >= 500
            || ! is_array($this->body)
            || $this->succeeded();
    }

    /**
     * How the failure reads in a log line or an alert.
     */
    public function describeFailure(): string
    {
        return $this->error !== '' ? $this->error : 'HTTP '.$this->http;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->body, $key, $default);
    }
}

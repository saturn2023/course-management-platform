<?php

namespace App\Enrolment\V2;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RTO Data API v2 client. Port of ams2_api().
 *
 * The payload rules live here rather than at the call sites, so no future
 * caller can reintroduce them:
 *
 *  - contact_email is never sent. It is the EMERGENCY CONTACT's address, and
 *    sending it overwrites or blanks that contact in RTO Data.
 *  - update payloads have empty strings and nulls stripped, so a field absent
 *    from this request is left alone rather than being blanked.
 *
 * Enrol payloads deliberately keep their empty values: ams2_enrol_args() sends
 * '' for AVETMISS fields the learner left blank, and RTO Data expects them.
 */
class Ams2ApiClient
{
    public const ENROL = 'enrol/';

    public const CHECK = 'check';

    public const UPDATE = 'update';

    public const RECEIVE = 'receive';

    /**
     * Never sent, on any endpoint.
     */
    public const FORBIDDEN_KEYS = ['contact_email'];

    /**
     * Create an enrolment.
     *
     * @param  array<string, mixed>  $args
     */
    public function enrol(array $args): Ams2ApiResult
    {
        return $this->post(self::ENROL, [
            'values' => http_build_query($this->scrub($args)),
        ]);
    }

    /**
     * Look up what RTO Data actually holds. Read-only, so safe to repeat.
     *
     * @param  array<string, mixed>  $args
     */
    public function check(array $args): Ams2ApiResult
    {
        return $this->post(self::CHECK, $this->scrub($args));
    }

    /**
     * Change fields on an existing enrolment.
     *
     * @param  array<string, mixed>  $args
     */
    public function update(array $args): Ams2ApiResult
    {
        return $this->post(self::UPDATE, $this->scrubForUpdate($args));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $endpoint, array $payload): Ams2ApiResult
    {
        $config = $this->config();

        /*
         * Refuse rather than call with a half-configured client. RTO Data
         * rejects a request with no Origin, and the enrol step would read that
         * rejection as "no clear answer" and park the row in verifying — a
         * misconfiguration would look like an RTO Data outage.
         */
        if ($missing = self::missingConfiguration()) {
            $reason = 'Not calling RTO Data: '.implode(' and ', $missing).' not set.';

            Log::error('[AMS2] api_not_configured', [
                'endpoint' => $endpoint,
                'missing' => $missing,
            ]);

            return new Ams2ApiResult(
                sent: false,
                error: $reason,
                http: 0,
                body: null,
                raw: '',
            );
        }

        $payload = $this->scrub($payload) + [
            'public_key' => $config['public_key'],
            'subdomain' => $config['subdomain'],
        ];

        try {
            $response = Http::withHeaders(['Origin' => $config['origin']])
                ->connectTimeout((int) $config['connect_timeout'])
                ->timeout((int) $config['timeout'])
                ->asJson()
                ->post($this->url($endpoint), $payload);
        } catch (Throwable $exception) {
            /*
             * Transport failure. The request may still have reached RTO Data,
             * so this is "unclear", never "it did not happen".
             */
            return new Ams2ApiResult(
                sent: false,
                error: $exception->getMessage(),
                http: 0,
                body: null,
                raw: '',
            );
        }

        $raw = (string) $response->body();

        return new Ams2ApiResult(
            sent: true,
            error: '',
            http: $response->status(),
            body: json_decode($raw, true),
            raw: mb_substr($raw, 0, 2000),
            rawFull: $raw,
        );
    }

    private function url(string $endpoint): string
    {
        return rtrim((string) $this->config()['base_url'], '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * Remove keys that must never reach RTO Data, whatever the endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function scrub(array $payload): array
    {
        return array_diff_key($payload, array_flip(self::FORBIDDEN_KEYS));
    }

    /**
     * Update payloads additionally drop empty values.
     *
     * RTO Data treats a supplied empty string as "set this to nothing", so an
     * unrelated field left blank in our payload would wipe real data.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function scrubForUpdate(array $payload): array
    {
        return array_filter(
            $this->scrub($payload),
            static fn (mixed $value): bool => ! is_null($value) && $value !== '',
        );
    }

    /**
     * Required settings that are empty, named as they appear in .env.
     *
     * Static so the processor can check the same rule before it takes its lock
     * or claims anything — a misconfigured run should stop at the top, not
     * halfway through a row. The instance guard below stays as a backstop for
     * any caller that bypasses the processor.
     *
     * @return array<int, string>
     */
    public static function missingConfiguration(): array
    {
        $missing = [];

        if (blank(config('services.enrolment_api.public_key'))) {
            $missing[] = 'ENROLMENT_API_PUBLIC_KEY';
        }

        if (blank(config('services.enrolment_api.origin'))) {
            $missing[] = 'ENROLMENT_API_ORIGIN';
        }

        return $missing;
    }

    /**
     * Credentials come from the shared ENROLMENT_API_* settings — same RTO Data
     * account and /api/v2 endpoint as the old flow, so there is no second copy
     * of the key to drift.
     *
     * Timeouts are v2's own: the old flow's 20s would push slow-but-successful
     * responses into the verify path far more often than WordPress does.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'base_url' => config('services.enrolment_api.base_url'),
            'public_key' => config('services.enrolment_api.public_key'),
            'subdomain' => config('services.enrolment_api.subdomain', 'amstraining'),
            'origin' => config('services.enrolment_api.origin'),
            'connect_timeout' => config('services.ams2.connect_timeout', 15),
            'timeout' => config('services.ams2.timeout', 90),
        ];
    }
}

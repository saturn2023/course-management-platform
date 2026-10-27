<?php

namespace App\Enrolment\V2;

/**
 * The largest document this server will actually accept.
 *
 * The reference offers AMS_DOC_MAX_BYTES (10MB) and leaves it there, because
 * its own host is configured to allow it. Hosting here is cPanel shared
 * hosting, where `upload_max_filesize` is routinely 2M–8M, and a file over that
 * is discarded by PHP before any application code runs: the request arrives
 * with an empty $_FILES and no useful error. Telling a learner they may upload
 * 10MB on a server that will silently drop 9MB of it is worse than offering
 * less.
 *
 * So the limit is the smallest of the configured ceiling, `upload_max_filesize`
 * and `post_max_size`, read at runtime rather than baked into the form text.
 *
 * `post_max_size` governs the whole request. v2 posts one file per request with
 * only a couple of small fields beside it, so `upload_max_filesize` is normally
 * the binding one — taking the minimum of both is the safe simplification
 * rather than an attempt to model the few hundred bytes of overhead.
 */
class Ams2DocumentLimit
{
    /**
     * The limit in bytes, never above the configured ceiling.
     */
    public function bytes(): int
    {
        $limit = (int) config('services.ams2.max_document_bytes', 10 * 1024 * 1024);

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $value = $this->iniBytes($setting);

            /*
             * A setting that parses to zero or less is not a zero-byte limit:
             * post_max_size documents 0 as unlimited, and an
             * upload_max_filesize of 0 is a misconfiguration this form cannot
             * usefully describe. Either way it is treated as not limiting.
             */
            if ($value > 0 && $value < $limit) {
                $limit = $value;
            }
        }

        return $limit;
    }

    /**
     * The limit as the form says it, e.g. "10MB" or "2.5MB".
     *
     * Rounded DOWN so the form never names a size the server would reject.
     */
    public function label(): string
    {
        $bytes = $this->bytes();

        // Under a megabyte, "0MB" would be the honest rounding and a useless
        // thing for a learner to read.
        if ($bytes < 1048576) {
            return max(1, (int) floor($bytes / 1024)).'KB';
        }

        $megabytes = $bytes / 1048576;

        if ($megabytes === floor($megabytes)) {
            return ((int) $megabytes).'MB';
        }

        return (floor($megabytes * 10) / 10).'MB';
    }

    /**
     * Whether PHP is the reason the limit is below the configured ceiling,
     * which is what an operator needs to know to raise it.
     */
    public function cappedByPhp(): bool
    {
        return $this->bytes() < (int) config('services.ams2.max_document_bytes', 10 * 1024 * 1024);
    }

    /**
     * Parse a php.ini size: plain bytes, or a 'K', 'M' or 'G' shorthand.
     */
    private function iniBytes(string $setting): int
    {
        $raw = trim((string) ini_get($setting));

        if ($raw === '') {
            return 0;
        }

        $value = (int) $raw;
        $unit = strtoupper(substr($raw, -1));

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
}

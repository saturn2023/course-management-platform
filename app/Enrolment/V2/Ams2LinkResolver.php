<?php

namespace App\Enrolment\V2;

use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Order;

/**
 * Resolves an enrolment link to an Ams2LinkInfo.
 *
 * Port of ams2_key_info() and ams2_link_info(). WordPress keeps links in its
 * own `secret_keys` table; here the same columns live on `enrolments`
 * (`secret_key`, `order_id`, `secret_base_url`, and the student relation for
 * the prefill), so an Enrolment row plays the part of a secret_keys row.
 *
 * The order of the checks is the point. An order that no longer resolves makes
 * the link invalid even though the key itself is well formed and present, so a
 * non-zero `order_id` is never enough on its own.
 */
class Ams2LinkResolver
{
    /**
     * A key must look like a key before the database is touched. Laravel's
     * keys are sha256 hex, which this matches; the bound is the reference's.
     */
    public const KEY_PATTERN = '/^[A-Za-z0-9]{16,128}$/';

    /**
     * How long after the ORDER was placed a link stays usable.
     */
    public const ORDER_LIFETIME_DAYS = 125;

    public function forSecretKey(?string $secretKey): Ams2LinkInfo
    {
        $secretKey = trim((string) $secretKey);

        if (! preg_match(self::KEY_PATTERN, $secretKey)) {
            return Ams2LinkInfo::invalid();
        }

        return $this->forEnrolment(
            Enrolment::query()->with('student')->where('secret_key', $secretKey)->first()
        );
    }

    /**
     * The same resolution from an Enrolment already in hand — the route reaches
     * a link by its token, not by its secret key.
     */
    public function forEnrolment(?Enrolment $enrolment): Ams2LinkInfo
    {
        if (! $enrolment || ! $enrolment->order_id) {
            return Ams2LinkInfo::invalid();
        }

        $order = Order::query()->find($enrolment->order_id);

        if (! $order) {
            return Ams2LinkInfo::invalid();
        }

        $query = $this->linkQuery($enrolment->secret_base_url);
        $code = strtoupper(trim((string) ($query['code'] ?? '')));

        return Ams2LinkInfo::resolved(
            expired: $this->hasExpired($enrolment, $order),
            orderId: (int) $enrolment->order_id,
            courseCode: $code,
            courseId: $this->courseIdFromCode($code),
            planId: max(0, (int) ($query['plan'] ?? 0)),

            /*
             * The employer a purchase-order enrolment belongs to. Laravel's
             * links do not carry `company` — CreateEnrolmentJob builds
             * secret_base_url with `code` and `plan` only — so in practice this
             * is always empty today. The parse is ported faithfully so the
             * value is picked up if the links ever carry it.
             */
            clientId: trim((string) ($query['company'] ?? '')),

            firstName: (string) ($enrolment->student?->first_name ?? ''),
            lastName: (string) ($enrolment->student?->last_name ?? ''),
            email: (string) ($enrolment->student?->email ?? ''),
        );
    }

    /**
     * RTO Data's course id for a link's `code`.
     *
     * Port of ams2_course_id_from_code(): a numeric code IS the id, and
     * anything else is looked up. The reference uses `ams_course_map()`; here
     * the mapping lives on the courses table.
     */
    public function courseIdFromCode(?string $code): int
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return 0;
        }

        if (ctype_digit($code)) {
            return (int) $code;
        }

        return (int) Course::query()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->value('ams_enrolment_code');
    }

    /**
     * Deliberate divergence: the reference expires a link on the order's age
     * alone (125 days). Laravel already issues links with their own
     * `enrolment_token_expires_at` (30 days), honoured by the old flow today,
     * so BOTH apply — whichever passes first.
     *
     * Taking only the reference's rule would quietly stretch every existing
     * link from 30 days to 125.
     */
    private function hasExpired(Enrolment $enrolment, Order $order): bool
    {
        if ($enrolment->enrolment_token_expires_at && now()->greaterThan($enrolment->enrolment_token_expires_at)) {
            return true;
        }

        /*
         * Expressed as a comparison rather than diffInDays(), which is an
         * absolute int on Carbon 2 and a signed float on Carbon 3.
         */
        return $order->created_at
            && $order->created_at->lessThan(now()->subDays(self::ORDER_LIFETIME_DAYS));
    }

    /**
     * @return array<string, mixed>
     */
    private function linkQuery(?string $url): array
    {
        $query = parse_url((string) $url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return [];
        }

        parse_str($query, $parsed);

        return $parsed;
    }
}

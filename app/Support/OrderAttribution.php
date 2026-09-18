<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * First-touch marketing attribution.
 *
 * The visitor's origin is detected the first time they arrive on the site with
 * recognisable campaign parameters and is then held in the Laravel session for
 * the rest of their visit. It is never overwritten by later page views, so a
 * visitor who lands from a Google Ad and browses Home -> Courses -> Checkout
 * still produces an order attributed to Google Ads.
 *
 * The stored value is the human-readable label shown in the admin.
 */
class OrderAttribution
{
    /** Session key holding the first-touch origin for this visitor. */
    public const SESSION_KEY = 'order_attribution.origin';

    public const DIRECT = 'Direct';
    public const GOOGLE_ADS = 'Google Ads';
    public const ORGANIC_GOOGLE = 'Organic Google';
    public const FACEBOOK_ADS = 'Facebook Ads';
    public const INSTAGRAM_ADS = 'Instagram Ads';
    public const META_ADS = 'Meta Ads';
    public const BING_ADS = 'Bing Ads';
    public const REFERRAL = 'Referral';
    public const EMAIL = 'Email';
    public const OTHER = 'Other';

    /**
     * Every origin the application can currently produce. Used for the Filament
     * filter options and badge colours. Adding a new source here plus a branch
     * in detect() is all that is required - no migration, no enum change.
     *
     * @var array<int, string>
     */
    public const ORIGINS = [
        self::GOOGLE_ADS,
        self::ORGANIC_GOOGLE,
        self::FACEBOOK_ADS,
        self::INSTAGRAM_ADS,
        self::META_ADS,
        self::BING_ADS,
        self::REFERRAL,
        self::EMAIL,
        self::DIRECT,
        self::OTHER,
    ];

    /** utm_medium values that indicate paid traffic. Compared lower-cased. */
    private const PAID_MEDIUMS = [
        'cpc',
        'ppc',
        'paid',
        'paidsearch',
        'paid_search',
        'paid-search',
        'paidsocial',
        'paid_social',
        'paid-social',
        'cpm',
        'cpv',
        'display',
        'adwords',
        'ads',
    ];

    /**
     * Store the visitor's origin on their very first recognisable touch.
     *
     * Nothing is written when the visit carries no campaign parameters, so a
     * visitor is never prematurely locked to Direct before a later page view
     * (or a campaign link opened in the same session) can be attributed.
     */
    public static function capture(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        // First touch wins: an origin already captured is never overwritten.
        if (filled($request->session()->get(self::SESSION_KEY))) {
            return;
        }

        $origin = self::detect($request);

        if ($origin === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $origin);
    }

    /**
     * The origin to store on an order created during this session.
     *
     * Falls back to Direct only at order creation time, when we know the
     * visitor reached checkout with no recognised campaign attribution.
     */
    public static function forOrder(): string
    {
        $origin = session(self::SESSION_KEY);

        return filled($origin) ? (string) $origin : self::DIRECT;
    }

    /**
     * Detect the origin of a single request, or null when the request carries
     * no recognisable marketing parameters.
     *
     * All comparisons are lower-cased, so Google / GOOGLE / google and
     * CPC / cpc are all handled.
     */
    public static function detect(Request $request): ?string
    {
        /*
         * Google Ads click identifiers. gclid is the common one; wbraid and
         * gbraid are the privacy-safe variants Google sends on iOS.
         */
        foreach (['gclid', 'wbraid', 'gbraid'] as $clickId) {
            if (filled($request->query($clickId))) {
                return self::GOOGLE_ADS;
            }
        }

        $source = Str::lower(trim((string) $request->query('utm_source', '')));
        $medium = Str::lower(trim((string) $request->query('utm_medium', '')));

        if ($source === '' && $medium === '') {
            return null;
        }

        $isPaid = in_array($medium, self::PAID_MEDIUMS, true);

        if (in_array($source, ['google', 'googleads', 'google ads', 'google-ads', 'google_ads', 'adwords'], true)) {
            // utm_source=google with a paid medium (cpc, ppc, ...) is Google
            // Ads. utm_source=google on its own is organic search.
            return $isPaid || $source !== 'google'
                ? self::GOOGLE_ADS
                : self::ORGANIC_GOOGLE;
        }

        if (in_array($source, ['bing', 'microsoft', 'msn'], true)) {
            return $isPaid ? self::BING_ADS : self::REFERRAL;
        }

        if (in_array($source, ['facebook', 'fb', 'facebook.com'], true)) {
            return $isPaid ? self::FACEBOOK_ADS : self::REFERRAL;
        }

        if (in_array($source, ['instagram', 'ig', 'instagram.com'], true)) {
            return $isPaid ? self::INSTAGRAM_ADS : self::REFERRAL;
        }

        if (in_array($source, ['meta', 'meta ads', 'meta_ads'], true)) {
            return self::META_ADS;
        }

        if ($medium === 'email' || in_array($source, ['email', 'newsletter', 'mailchimp'], true)) {
            return self::EMAIL;
        }

        if ($medium === 'referral') {
            return self::REFERRAL;
        }

        // A campaign was present but is not one we recognise yet.
        return self::OTHER;
    }
}

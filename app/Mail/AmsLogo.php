<?php

namespace App\Mail;

/**
 * The AMS Training logo as it appears in the shared mail header.
 *
 * The content ID is referenced from two places that cannot see each other —
 * the trait that embeds the file and the Blade header that renders the <img> —
 * so it is defined once here.
 */
final class AmsLogo
{
    public const CID = 'ams-logo';

    public const MIME = 'image/png';

    public static function path(): string
    {
        return public_path('images/AMS-Training-Logo.png');
    }

    public static function exists(): bool
    {
        return is_file(static::path());
    }
}

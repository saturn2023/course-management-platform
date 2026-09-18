<?php

namespace App\Mail\Concerns;

use App\Mail\AmsLogo;
use Symfony\Component\Mime\Email;

/**
 * Puts the AMS Training logo in the shared mail header.
 *
 * The logo is embedded in the message under a fixed content ID rather than
 * linked to a URL. Linking would mean the logo only appears when the site is
 * publicly reachable and the recipient's client loads remote images — Outlook
 * blocks those by default. Embedding always renders, at the cost of ~26KB.
 *
 * $message is not in scope inside Blade components, so the header cannot call
 * embed() itself; it references cid:ams-logo and this trait supplies it.
 */
trait EmbedsAmsLogo
{
    protected function embedAmsLogo(): static
    {
        if (! AmsLogo::exists()) {
            return $this;
        }

        return $this->withSymfonyMessage(function (Email $email): void {
            $email->embedFromPath(AmsLogo::path(), AmsLogo::CID, AmsLogo::MIME);
        });
    }
}

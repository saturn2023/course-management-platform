{{--
    Reusable site footer.

    Usage — at the end of the page body:
        <x-layout.footer />

    - Sits alongside <x-layout.header /> in the layout namespace. Like the
      header, this project has no shared HTML layout, so each page mounts it.
    - Navigation destinations are the SAME URLs the header uses, so the two stay
      consistent. They are kept in one $navLinks array here rather than shared
      with the header, which would couple the two components — the header's own
      array is untouched.
    - Fonts: Outfit (navigation + bottom row, Google Fonts) and azo-sans-web
      (Contact us button, Adobe Fonts kit sat8ost). Both already loaded in the
      page <head>.
    - Colours reuse the AMS palette used across the homepage sections
      (#39B54A green).
    - No JavaScript. All styles scoped under .ams-footer, so nothing here can
      reach the header — which stays dark charcoal with white navigation.
--}}

@php
    /*
     * Same destinations as the header's nav, in the reference footer's order.
     *
     * Note these are the rebuild's placeholder URLs: some are not routed yet
     * — /about, /student-information and /contact are what the header points
     * at, and /certificate-check is where the header's modal button would go if
     * it were a link. Reusing them keeps header and footer consistent; routing
     * them is a separate job and no new route was invented here.
     */
    $navLinks = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'About', 'url' => '/about'],
        ['label' => 'Certificate Check', 'url' => '/certificate-check'],
        ['label' => 'Student Info', 'url' => '/student-information'],
        ['label' => 'Contact', 'url' => '/contact'],
    ];
@endphp

<footer class="ams-footer">
    <style>
        /* Scoped to .ams-footer — nothing here can reach the header or any
           homepage section. */
        .ams-footer {
            --ams-green: #39B54A;
            --ams-green-dark: #2f9e3f;
            --ams-footer-ink: #555555;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
            --font-btn: 'azo-sans-web', 'Segoe UI', Arial, sans-serif;

            width: 100%;
            margin: 0;
            padding: 0;
            background: #F2F2F2;
        }

        .ams-footer * {
            box-sizing: border-box;
        }

        /* Elementor "Content Width: Boxed / 1360px", the same boxed width the
           homepage sections use. The gutter keeps content off the edges below
           that width. */
        .ams-footer__inner {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .ams-footer__main {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            padding: 80px 0;
        }

        .ams-footer__logo {
            flex: 0 0 auto;
            display: inline-block;
            line-height: 0;
        }

        .ams-footer__logo:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 4px;
        }

        /* Width only, height auto — the 236x167 source keeps its own ratio and
           cannot be stretched. */
        .ams-footer__logo img {
            display: block;
            width: 100px;
            height: auto;
        }

        .ams-footer__nav ul {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .ams-footer__nav-link {
            color: var(--ams-footer-ink);
            font-family: var(--font-body);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.3;
            text-decoration: none;
            white-space: nowrap;
            transition: color 0.15s ease;
        }

        .ams-footer__nav-link:hover {
            color: var(--ams-green);
        }

        .ams-footer__nav-link:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 3px;
        }

        /*
         * The AMS three-part treatment: two narrow bars then the label block,
         * all one anchor so the whole thing is a single click target. Built from
         * elements, not an image.
         */
        .ams-footer__contact {
            flex: 0 0 auto;
            display: inline-flex;
            flex-direction: row;
            align-items: stretch;
            gap: 5px;
            text-decoration: none;
        }

        .ams-footer__contact-bars {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 5px;
        }

        /*
         * align-items: stretch above is what keeps the bars exactly the label
         * block's height, so no bar height is hard-coded to match the padding.
         */
        .ams-footer__contact-bars span {
            display: block;
            width: 10px;
            background: var(--ams-green);
            transition: background-color 0.15s ease;
        }

        .ams-footer__contact-label {
            display: inline-flex;
            align-items: center;
            padding: 18px 30px;
            background: var(--ams-green);
            border: 0;
            border-radius: 0;
            color: #FFFFFF;
            font-family: var(--font-btn);
            font-size: 16px;
            font-weight: 300;
            line-height: 1;
            white-space: nowrap;
            transition: background-color 0.15s ease;
        }

        /* Hovering anywhere on the treatment darkens all three parts together,
           reinforcing that it is one control. */
        .ams-footer__contact:hover .ams-footer__contact-bars span,
        .ams-footer__contact:hover .ams-footer__contact-label {
            background: var(--ams-green-dark);
        }

        .ams-footer__contact:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 4px;
        }

        /* Confined to the boxed width, as in the reference. */
        .ams-footer__divider {
            border-top: 1px solid #D5D5D5;
        }

        /*
         * 1fr auto 1fr puts the copyright in the footer's true centre — it stays
         * centred regardless of how wide the address or RTO text is, which a
         * space-between row would not do.
         */
        .ams-footer__bottom {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 20px;
            padding: 26px 0 30px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 300;
            line-height: 1.3;
        }

        .ams-footer__office,
        .ams-footer__copyright,
        .ams-footer__rto {
            margin: 0;
        }

        .ams-footer__office {
            color: var(--ams-green);
            text-align: left;
        }

        .ams-footer__copyright {
            color: #4a4a4a;
            text-align: center;
        }

        .ams-footer__rto {
            color: var(--ams-green);
            text-align: right;
        }

        /* Tablet — same 900px breakpoint the homepage sections switch at. The
           row stacks so the links never get squeezed together. */
        @media (max-width: 900px) {
            .ams-footer__main {
                flex-direction: column;
                justify-content: center;
                gap: 28px;
                padding: 56px 0;
            }

            .ams-footer__nav ul {
                gap: 16px 26px;
            }

            .ams-footer__bottom {
                gap: 14px;
            }
        }

        @media (max-width: 540px) {
            .ams-footer__main {
                padding: 44px 0;
                gap: 24px;
            }

            .ams-footer__nav ul {
                flex-direction: column;
                gap: 14px;
            }

            /* The three-column grid would crush this text, so it becomes one
               centred column. */
            .ams-footer__bottom {
                grid-template-columns: 1fr;
                justify-items: center;
                gap: 12px;
                padding: 22px 0 26px;
                text-align: center;
            }

            .ams-footer__office,
            .ams-footer__copyright,
            .ams-footer__rto {
                text-align: center;
            }
        }
    </style>

    <div class="ams-footer__inner">
        <div class="ams-footer__main">
            <a class="ams-footer__logo" href="/" aria-label="AMS Training home">
                <img src="/images/ams-logo.webp" alt="AMS Training" width="236" height="167">
            </a>

            <nav class="ams-footer__nav" aria-label="Footer navigation">
                <ul>
                    @foreach ($navLinks as $link)
                        <li>
                            <a class="ams-footer__nav-link" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- One anchor, three visual parts. The bars are decorative only. --}}
            <a class="ams-footer__contact" href="/contact">
                <span class="ams-footer__contact-bars" aria-hidden="true">
                    <span></span>
                    <span></span>
                </span>

                <span class="ams-footer__contact-label">Contact us</span>
            </a>
        </div>

        <div class="ams-footer__divider"></div>

        <div class="ams-footer__bottom">
            <p class="ams-footer__office">Office: 1/22 Beresford Road Yarrawonga NT 0830</p>

            {{-- Year from the server, so this never needs editing again. --}}
            <p class="ams-footer__copyright">Copyright {{ now()->year }} AMS Training</p>

            <p class="ams-footer__rto">RTO#45735</p>
        </div>
    </div>
</footer>

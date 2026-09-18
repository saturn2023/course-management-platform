{{--
    Reusable homepage section: PACKAGE ONLINE REFRESHER COURSES

    Usage:
        <x-home.package-refresher-courses :packages="$packages" />

    - Accepts a CoursePackage collection (renders active + show_on_homepage,
      ordered by display_order then id). 3/2/1 responsive.
    - Each card stacks 1–3 images in a fixed-height area, split to equal heights,
      each with an optional icon overlaid top-right, so every card is the same
      height regardless of image count.
    - Whole card links to course_url (button is a styled span; disabled if no URL).
    - Fonts: Normalidad Compact (heading/banner/button), Outfit (codes/title) —
      must be loaded in the page <head>. Styles scoped under .ams-packages.
--}}

@props(['packages' => collect()])

@php
    $visiblePackages = collect($packages)
        ->filter(fn ($p) => $p->status === 'active' && $p->show_on_homepage)
        ->sortBy([
            ['display_order', 'asc'],
            ['id', 'asc'],
        ])
        ->values();

    $nrtLogoPath = 'images/nationally-recognised-training-logo.png';
    $hasNrtLogo = file_exists(public_path($nrtLogoPath));

    // Root-relative URL so assets resolve against the current host (incl. ngrok).
    $publicUrl = function (?string $path): ?string {
        if (blank($path)) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }
        return '/storage/' . ltrim($path, '/');
    };

    // Build the ordered image/icon items (1–3) that actually have an image.
    $itemsFor = function ($p) {
        $items = [];
        foreach ([1, 2, 3] as $i) {
            $image = $p->{"image_{$i}"} ?? null;
            if (filled($image)) {
                $items[] = ['image' => $image, 'icon' => $p->{"icon_{$i}"} ?? null];
            }
        }
        return $items;
    };
@endphp

<section class="ams-packages">
    <style>
        /* Scoped to .ams-packages so this block never affects other pages. */
        .ams-packages {
            --ams-green: #39B54A;
            --ams-green-dark: #2f9e3f;
            /* Same pale-green button pair as the single-course cards. */
            --ams-green-soft: #E2EEE4;
            --ams-green-soft-hover: #d3e7c8;
            --ams-title: #2C2C2C;
            --ams-subtitle: #828282;
            --font-head: 'normalidad-compact', 'Segoe UI', Arial, sans-serif;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
            background: #FFFFFF;
            font-family: var(--font-body);
            color: #333;
            padding: 60px 30px;
        }

        .ams-packages * {
            box-sizing: border-box;
        }

        /* Same boxed width as .ams-refresher .arc-inner so both sections align. */
        .ams-packages .apc-inner {
            max-width: 1360px;
            margin: 0 auto;
        }

        /* Header: heading + subtitle left, NRT logo right. */
        .ams-packages .apc-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 30px;
            margin-bottom: 34px;
        }

        .ams-packages .apc-title {
            color: var(--ams-green);
            font-family: var(--font-head);
            font-size: 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.6px;
            line-height: 1.2;
            margin: 0 0 10px;
        }

        .ams-packages .apc-subtitle {
            color: var(--ams-subtitle);
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 300;
            line-height: 1.3;
            margin: 0;
            max-width: 640px;
        }

        .ams-packages .apc-logo {
            flex: 0 0 auto;
        }

        .ams-packages .apc-logo img {
            max-height: 90px;
            width: auto;
            display: block;
        }

        /* 3-column desktop grid; equal-height cards. */
        .ams-packages .apc-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            align-items: stretch;
        }

        /*
         * The card background is only ever visible in the info area below the
         * green banner — the image stack and banner paint over the rest — so
         * this is what tints the codes/title/button block.
         */
        .ams-packages .apc-card {
            background: #F5F5F5;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.15s ease;
        }

        .ams-packages a.apc-card:hover {
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.10);
        }

        .ams-packages .apc-card:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 2px;
        }

        /*
         * Fixed-height image stack. Segments share the height equally (flex:1),
         * so 1, 2 or 3 images always fill the same total area — cards stay the
         * same height. The gap reveals the green background as the divider; it
         * comes out of the segments, never out of the stack's total height, so
         * changing it never shifts the banner or unbalances the cards.
         */
        .ams-packages .apc-stack {
            display: flex;
            flex-direction: column;
            gap: 18px;
            background: var(--ams-green);
            height: 460px;
        }

        .ams-packages .apc-seg {
            position: relative;
            flex: 1 1 0;
            min-height: 0;
            overflow: hidden;
        }

        .ams-packages .apc-seg img.apc-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .ams-packages .apc-seg-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2c2c2c, #4a4a4a);
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        /* Icon overlaid top-right of each image segment. */
        .ams-packages .apc-seg-icon {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            overflow: hidden;
            z-index: 2;
        }

        .ams-packages .apc-seg-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Full-width green banner under the stack. */
        .ams-packages .apc-banner {
            background: var(--ams-green);
            color: #ffffff;
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: center;
            padding: 12px 10px;
        }

        /* 30px vertical rhythm, matching the single-course cards. */
        .ams-packages .apc-body {
            padding: 30px 16px 0;
        }

        .ams-packages .apc-codes {
            color: var(--ams-green);
            font-family: var(--font-body);
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            margin: 0 0 30px;
        }

        .ams-packages .apc-name {
            color: var(--ams-title);
            font-family: var(--font-body);
            font-size: 22px;
            font-weight: 500;
            line-height: 1.25;
            margin: 0;
            /* Reserve two lines so spacing is even across cards. */
            white-space: pre-line;
            min-height: 2.5em;
        }

        /* margin-top: auto pins the button to the card bottom so all buttons line
           up even when one title wraps to fewer lines. */
        .ams-packages .apc-actions {
            padding: 30px 16px 30px;
            margin-top: auto;
        }

        /* Pale green, matching the single-course cards. */
        .ams-packages .apc-btn {
            display: block;
            width: 100%;
            text-align: center;
            background: var(--ams-green-soft);
            color: var(--ams-green);
            border: none;
            padding: 16px;
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-decoration: none;
        }

        .ams-packages a.apc-card:hover .apc-btn {
            background: var(--ams-green-soft-hover);
        }

        .ams-packages .apc-btn.is-disabled {
            background: #eef0f2;
            color: #9aa0a8;
            cursor: not-allowed;
        }

        .ams-packages .apc-empty {
            text-align: center;
            color: #8a8f97;
            padding: 40px 0;
            font-size: 1.05rem;
        }

        /* Tablet: 2 columns, stacked header. */
        @media (max-width: 900px) {
            .ams-packages .apc-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .ams-packages .apc-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 18px;
            }
            .ams-packages .apc-subtitle {
                font-size: 16px;
            }
            .ams-packages .apc-stack {
                height: 420px;
            }
        }

        /* Mobile: 1 column. */
        @media (max-width: 540px) {
            .ams-packages {
                padding: 40px 18px;
            }
            .ams-packages .apc-grid {
                grid-template-columns: 1fr;
            }
            .ams-packages .apc-title {
                font-size: 22px;
                letter-spacing: 1px;
            }
            .ams-packages .apc-stack {
                height: 400px;
            }
        }
    </style>

    <div class="apc-inner">
        <div class="apc-head">
            <div class="apc-head-text">
                <h2 class="apc-title">Package Online Refresher Courses</h2>
                <p class="apc-subtitle">
                    Keep your qualifications up to date with our single online refreshers.
                </p>
            </div>

            @if ($hasNrtLogo)
                <div class="apc-logo">
                    <img src="/{{ $nrtLogoPath }}" alt="Nationally Recognised Training">
                </div>
            @endif
        </div>

        @if ($visiblePackages->isEmpty())
            <p class="apc-empty">No course packages are currently available.</p>
        @else
            <div class="apc-grid">
                @foreach ($visiblePackages as $package)
                    @php
                        $items = $itemsFor($package);
                        $bannerText = filled($package->banner_text) ? $package->banner_text : 'PACKAGE ONLINE REFRESHER';
                        $hasUrl = filled($package->course_url);
                    @endphp

                    @if ($hasUrl)
                        <a class="apc-card" href="{{ $package->course_url }}">
                    @else
                        <article class="apc-card">
                    @endif
                        <div class="apc-stack">
                            @forelse ($items as $item)
                                @php $imgUrl = $publicUrl($item['image']); $iconUrl = $publicUrl($item['icon']); @endphp
                                <div class="apc-seg">
                                    @if ($iconUrl)
                                        <span class="apc-seg-icon"><img src="{{ $iconUrl }}" alt=""></span>
                                    @endif
                                    <img class="apc-img" src="{{ $imgUrl }}" alt="{{ $package->title }}">
                                </div>
                            @empty
                                <div class="apc-seg"><div class="apc-seg-placeholder">AMS Training</div></div>
                            @endforelse
                        </div>

                        <div class="apc-banner">{{ $bannerText }}</div>

                        <div class="apc-body">
                            @if (filled($package->codes))
                                <div class="apc-codes">{{ $package->codes }}</div>
                            @endif
                            <h3 class="apc-name">{{ $package->title }}</h3>
                        </div>

                        <div class="apc-actions">
                            <span class="apc-btn @unless ($hasUrl) is-disabled @endunless">View course</span>
                        </div>
                    @if ($hasUrl)
                        </a>
                    @else
                        </article>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>

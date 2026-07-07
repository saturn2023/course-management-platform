{{--
    Reusable homepage section: SINGLE ONLINE REFRESHER COURSES

    Usage:
        <x-home.online-refresher-courses :courses="$courses" />

    - Accepts a Course collection (renders active + show_on_homepage, ordered by
      display_order then id). Supports any number of courses (4/2/1 responsive).
    - Visual spec recreated from the Elementor template:
      green #39B54A, section #F5F5F5, title #2C2C2C, subtitle #828282,
      pale-green button #E2EEE4; headings/banner/button use Normalidad Compact,
      body/code/title use Outfit. (Fonts must be loaded in the page <head>.)
    - All styles scoped under .ams-refresher so other pages are unaffected.
--}}

@props(['courses' => collect()])

@php
    $visibleCourses = collect($courses)
        ->filter(fn ($course) => $course->status === 'active' && $course->show_on_homepage)
        ->sortBy([
            ['display_order', 'asc'],
            ['id', 'asc'],
        ])
        ->values();

    /*
     | Nationally Recognised Training logo (header, right). No DB field — drop
     | the asset at public/images/nationally-recognised-training-logo.png and it
     | appears automatically. Omitted cleanly if missing (never a broken image).
     */
    $nrtLogoPath = 'images/nationally-recognised-training-logo.png';
    $hasNrtLogo = file_exists(public_path($nrtLogoPath));

    /*
     | Build a root-relative URL so images resolve against the current host
     | (localhost, ngrok, or production) instead of a fixed APP_URL. Absolute
     | URLs from Storage::url() break behind ngrok's host rewriting. The public
     | disk is served at /storage via the storage:link symlink.
     */
    $publicUrl = function (?string $path): ?string {
        if (blank($path)) {
            return null;
        }

        // Already absolute or already root-relative — leave as-is.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/' . ltrim($path, '/');
    };
@endphp

<section class="ams-refresher">
    <style>
        /* Scoped to .ams-refresher so this block never affects other pages. */
        .ams-refresher {
            --ams-green: #39B54A;
            --ams-green-soft: #E2EEE4;
            --ams-green-soft-hover: #d3e7c8;
            --ams-title: #2C2C2C;
            --ams-subtitle: #828282;
            --font-head: 'normalidad-compact', 'Segoe UI', Arial, sans-serif;
            --font-body: 'Outfit', 'Segoe UI', Arial, sans-serif;
            background: #F5F5F5;
            font-family: var(--font-body);
            color: #333;
            padding: 60px 30px;
        }

        .ams-refresher * {
            box-sizing: border-box;
        }

        .ams-refresher .arc-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header: heading + subtitle on the left, NRT logo on the right. */
        .ams-refresher .arc-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 30px;
            margin-bottom: 34px;
        }

        .ams-refresher .arc-head-text {
            text-align: left;
        }

        .ams-refresher .arc-title {
            color: var(--ams-green);
            font-family: var(--font-head);
            font-size: 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.6px;
            line-height: 1.2;
            margin: 0 0 10px;
        }

        .ams-refresher .arc-subtitle {
            color: var(--ams-subtitle);
            font-family: var(--font-body);
            font-size: 20px;
            font-weight: 300;
            line-height: 1.3;
            margin: 0;
            max-width: 640px;
        }

        .ams-refresher .arc-logo {
            flex: 0 0 auto;
        }

        .ams-refresher .arc-logo img {
            max-height: 90px;
            width: auto;
            display: block;
        }

        /* 4-column desktop grid; rows stretch so cards are equal height. */
        .ams-refresher .arc-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            align-items: stretch;
        }

        /* Flat cards (no border/shadow/radius) to match the Elementor design. */
        .ams-refresher .arc-card {
            background: #ffffff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .ams-refresher .arc-image-wrap {
            position: relative;
            width: 100%;
            height: 320px;
        }

        .ams-refresher .arc-image-wrap img.arc-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Placeholder shown when no image is set — never a broken image. */
        .ams-refresher .arc-img-placeholder {
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
            font-size: 0.95rem;
        }

        /*
         * Category icon overlaid top-right of the image. The AMS icon assets
         * already include their own coloured circle, so this just sizes,
         * positions and clips them. Corners clipped for safety.
         */
        .ams-refresher .arc-icon {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            z-index: 2;
        }

        .ams-refresher .arc-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Full-width green banner directly beneath the image. */
        .ams-refresher .arc-banner {
            background: var(--ams-green);
            color: #ffffff;
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: center;
            padding: 10px;
        }

        /* Even 30px vertical rhythm: banner → code. */
        .ams-refresher .arc-body {
            padding: 30px 16px 0;
        }

        .ams-refresher .arc-code {
            color: var(--ams-green);
            font-family: var(--font-body);
            font-size: 17px;
            font-weight: 600;
            text-transform: uppercase;
            /* 30px between code and title. */
            margin: 0 0 30px;
        }

        .ams-refresher .arc-name {
            color: var(--ams-title);
            font-family: var(--font-body);
            font-size: 25px;
            font-weight: 500;
            line-height: 1.2;
            margin: 0;
            /* Honour the manual line break entered in `card_title`, and reserve
               two lines so the code → title → button spacing stays equal across
               every card regardless of title length. */
            white-space: pre-line;
            min-height: 2.4em;
        }

        /* 30px between title and button, and 30px from button to card bottom. */
        .ams-refresher .arc-actions {
            padding: 30px 16px 30px;
        }

        .ams-refresher .arc-btn {
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
            transition: background 0.15s ease;
        }

        .ams-refresher .arc-btn:hover {
            background: var(--ams-green-soft-hover);
        }

        .ams-refresher .arc-btn:focus-visible {
            outline: 3px solid var(--ams-green);
            outline-offset: 2px;
        }

        /* Disabled state when course_url is missing — same footprint. */
        .ams-refresher .arc-btn.is-disabled {
            background: #eef0f2;
            color: #9aa0a8;
            cursor: not-allowed;
            pointer-events: none;
        }

        .ams-refresher .arc-empty {
            text-align: center;
            color: #8a8f97;
            padding: 40px 0;
            font-size: 1.05rem;
        }

        /* Tablet: 2 columns, stacked header. */
        @media (max-width: 900px) {
            .ams-refresher .arc-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .ams-refresher .arc-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 18px;
            }
            .ams-refresher .arc-subtitle {
                font-size: 16px;
            }
            .ams-refresher .arc-image-wrap {
                height: 300px;
            }
        }

        /* Mobile: 1 column, full-width cards. */
        @media (max-width: 540px) {
            .ams-refresher {
                padding: 40px 18px;
            }
            .ams-refresher .arc-grid {
                grid-template-columns: 1fr;
            }
            .ams-refresher .arc-title {
                font-size: 22px;
                letter-spacing: 1px;
            }
            .ams-refresher .arc-name {
                font-size: 22px;
            }
        }
    </style>

    <div class="arc-inner">
        <div class="arc-head">
            <div class="arc-head-text">
                <h2 class="arc-title">Single Online Refresher Courses</h2>
                <p class="arc-subtitle">
                    Keep your qualifications up to date with our single online refreshers.
                </p>
            </div>

            @if ($hasNrtLogo)
                <div class="arc-logo">
                    {{-- Root-relative so it resolves against the current host (incl. ngrok). --}}
                    <img src="/{{ $nrtLogoPath }}" alt="Nationally Recognised Training">
                </div>
            @endif
        </div>

        @if ($visibleCourses->isEmpty())
            <p class="arc-empty">No courses are currently available.</p>
        @else
            <div class="arc-grid">
                @foreach ($visibleCourses as $course)
                    @php
                        $imageUrl = $publicUrl($course->image_path);
                        $iconUrl = $publicUrl($course->icon_path);
                        $bannerText = filled($course->banner_text)
                            ? $course->banner_text
                            : '100% ONLINE REFRESHER';
                        // Optional two-line card heading; falls back to the
                        // canonical title. Newlines render via white-space: pre-line.
                        $displayTitle = filled($course->card_title)
                            ? $course->card_title
                            : $course->title;
                        $hasUrl = filled($course->course_url);
                    @endphp

                    <article class="arc-card">
                        <div class="arc-image-wrap">
                            @if ($iconUrl)
                                <span class="arc-icon">
                                    <img src="{{ $iconUrl }}" alt="{{ $course->title }} category icon">
                                </span>
                            @endif

                            @if ($imageUrl)
                                <img class="arc-img" src="{{ $imageUrl }}" alt="{{ $course->title }}">
                            @else
                                <div class="arc-img-placeholder">AMS Training</div>
                            @endif
                        </div>

                        <div class="arc-banner">{{ $bannerText }}</div>

                        <div class="arc-body">
                            @if (filled($course->code))
                                <div class="arc-code">{{ $course->code }}</div>
                            @endif
                            <h3 class="arc-name">{{ $displayTitle }}</h3>
                        </div>

                        <div class="arc-actions">
                            @if ($hasUrl)
                                <a href="{{ $course->course_url }}" class="arc-btn">View course</a>
                            @else
                                <span class="arc-btn is-disabled" aria-disabled="true">View course</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{--
    Reusable hero banner with a background video (sits directly below the header).

    Usage:
        <x-home.hero-banner />
        <x-home.hero-banner eyebrow="RTO 45735" heading="Your heading here" />

    - Full-width background video, dark overlay for text contrast, left-aligned text.
    - Text is passed via props (eyebrow + heading) with sensible defaults.
    - Graceful fallback: poster image shows while the video loads / if autoplay
      is blocked; a dark charcoal background shows if even the poster is missing.
      Never a broken image.
    - Drop the assets here (root-relative paths, so they work over ngrok too):
        video : public/videos/hero-mining-construction.mp4  (+ optional .webm)
        poster: public/images/hero-poster.jpg
    - All styles scoped under .ams-hero so other pages are unaffected.
--}}

@props([
    'eyebrow' => 'RTO 45735',
    'heading' => 'Australia’s leading provider of Mining and Construction courses',
    'videoMp4' => '/videos/hero-mining-construction.mp4',
    'videoWebm' => '/videos/hero-mining-construction.webm',
    'poster' => '/images/hero-poster.jpg',
])

@php
    // Only emit a <source> for asset files that actually exist, so we never
    // point the browser at a missing file.
    $mp4Exists = $videoMp4 && file_exists(public_path(ltrim($videoMp4, '/')));
    $webmExists = $videoWebm && file_exists(public_path(ltrim($videoWebm, '/')));
    $posterExists = $poster && file_exists(public_path(ltrim($poster, '/')));
    $hasVideo = $mp4Exists || $webmExists;
@endphp

<section class="ams-hero">
    <style>
        /* Scoped to .ams-hero so this block never affects other pages. */
        .ams-hero {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            min-height: clamp(380px, 42vw, 640px);
            overflow: hidden;
            /* Ultimate fallback if neither video nor poster is available. */
            background: #2c2c2c;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .ams-hero * {
            box-sizing: border-box;
        }

        /* Background video / poster fill the banner. */
        .ams-hero__media {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            z-index: 0;
        }

        /* Poster fallback shown when there is no playable video. */
        .ams-hero__poster {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            z-index: 0;
        }

        /* Dark gradient: stronger on the left for legible left-aligned text. */
        .ams-hero__overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(
                90deg,
                rgba(0, 0, 0, 0.68) 0%,
                rgba(0, 0, 0, 0.45) 45%,
                rgba(0, 0, 0, 0.15) 100%
            );
        }

        .ams-hero__inner {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        .ams-hero__eyebrow {
            margin: 0 0 14px;
            color: #ffffff;
            font-family: 'normalidad-compact', 'Segoe UI', Arial, sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .ams-hero__heading {
            margin: 0;
            color: #ffffff;
            font-family: 'normalidad-wide', 'Segoe UI', Arial, sans-serif;
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            line-height: 1.15;
            max-width: 640px;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
        }

        @media (max-width: 640px) {
            .ams-hero {
                min-height: 340px;
            }
            .ams-hero__inner {
                padding: 28px 20px;
            }
        }

        /* Respect users who prefer reduced motion — hide the moving video and
           lean on the poster/overlay instead. */
        @media (prefers-reduced-motion: reduce) {
            .ams-hero__media {
                display: none;
            }
        }
    </style>

    @if ($hasVideo)
        <video
            class="ams-hero__media"
            autoplay
            muted
            loop
            playsinline
            @if ($posterExists) poster="{{ $poster }}" @endif
            aria-hidden="true"
            tabindex="-1"
        >
            @if ($mp4Exists)
                <source src="{{ $videoMp4 }}" type="video/mp4">
            @endif
            @if ($webmExists)
                <source src="{{ $videoWebm }}" type="video/webm">
            @endif
        </video>
    @elseif ($posterExists)
        <div class="ams-hero__poster" style="background-image: url('{{ $poster }}');" aria-hidden="true"></div>
    @endif

    <div class="ams-hero__overlay" aria-hidden="true"></div>

    <div class="ams-hero__inner">
        @if (filled($eyebrow))
            <p class="ams-hero__eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="ams-hero__heading">{{ $heading }}</h1>
    </div>
</section>

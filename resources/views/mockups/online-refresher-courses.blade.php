{{-- resources/views/mockups/online-refresher-courses.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Refresher Courses</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #ffffff;
            color: #333;
            padding: 30px 20px;
        }

        .courses-section {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Spacing between the two sections */
        .courses-section + .courses-section {
            margin-top: 55px;
        }

        .section-title {
            color: #5bb143;
            font-size: 2.2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 6px;
            margin-bottom: 25px;
        }

        /* ===== SINGLE COURSES GRID: 4 columns desktop ===== */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .course-card {
            background: #fdfdfd;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Image area — full image shown, including built-in green code banner.
           No fixed aspect-ratio / no object-fit crop so the banner is never cut off. */
        .card-image-wrap {
            position: relative;
            width: 100%;
        }

        .card-image-wrap img.course-img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Icon top-left.
           The icon PNGs already include their own circular coloured background,
           so the wrapper only positions and sizes them — no extra circle,
           background colour, or shadow here. */
        .card-icon {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 46px;
            height: 46px;
            display: block;
            z-index: 2;
        }

        .card-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .card-body {
            padding: 12px 4px 0 4px;
        }

        .course-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.25;
            margin-bottom: 10px;
            min-height: 2.6em;
        }

        /* Bottom row: button on the left, price on the right,
           with equal side padding so the price doesn't touch the edge. */
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 16px 14px 16px;
            margin-top: auto;
        }

        .card-footer .price {
            margin-right: 0;
        }

        .view-btn {
            background: #e3f0dc;
            color: #4a9637;
            border: none;
            padding: 9px 16px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            border-radius: 2px;
        }

        .view-btn:hover {
            background: #d3e7c8;
        }

        .price {
            color: #f15a22;
            font-size: 1.2rem;
            font-weight: 700;
        }

        /* ===== PACKAGE COURSES GRID: 3 columns desktop ===== */
        .package-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .package-card {
            background: #fdfdfd;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Package image area — the image already contains the green code banners,
           so NO ribbon/label is added in markup. Icons are overlaid via absolute
           positioning over the relevant image segments. */
        .package-image-wrap {
            position: relative;
            width: 100%;
        }

        .package-image-wrap img.package-img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Icons overlaid on each course segment of the stacked package image.
           Positions are percentage-based so they track the segments as the
           image scales. Tweak the `top` values to match the final artwork. */
        .pkg-icon {
            position: absolute;
            left: 10px;
            width: 42px;
            height: 42px;
            display: block;
            z-index: 2;
        }

        .pkg-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        /* Codes line under the package image */
        .package-codes {
            font-size: 0.8rem;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 12px 4px 0 4px;
        }

        .package-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.3;
            padding: 6px 4px 0 4px;
            margin-bottom: 12px;
            min-height: 3.4em;
        }

        /* ===== RESPONSIVE ===== */

        /* Tablet: 2 columns */
        @media (max-width: 900px) {
            .courses-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 18px;
            }
            .package-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 18px;
            }
            .section-title {
                font-size: 1.7rem;
                letter-spacing: 4px;
            }
        }

        /* Mobile: 1 column */
        @media (max-width: 540px) {
            .courses-grid {
                grid-template-columns: 1fr;
            }
            .package-grid {
                grid-template-columns: 1fr;
            }
            .section-title {
                font-size: 1.4rem;
                letter-spacing: 3px;
            }
        }
    </style>
</head>
<body>

    {{-- ============================================================= --}}
    {{-- SECTION 1: SINGLE ONLINE REFRESHER COURSES                    --}}
    {{-- ============================================================= --}}
    <section class="courses-section">
        <h1 class="section-title">Single Online Refresher Courses.</h1>

        <div class="courses-grid">

            {{-- Card 1 — larger icon test --}}
            <div class="course-card">
                <div class="card-image-wrap">
                    <span class="card-icon">
                        <img src="{{ asset('images/icons/heights-icon.png') }}" alt="Heights icon">
                    </span>
                    <img class="course-img" src="{{ asset('images/courses/work-safely-heights.jpg') }}" alt="Work Safely At Heights">
                </div>
                <div class="card-body">
                    <div class="course-title">Work Safely At Heights</div>
                </div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$110.00</span>
                </div>
            </div>

            {{-- Card 2 --}}
            <div class="course-card">
                <div class="card-image-wrap">
                    <span class="card-icon">
                        <img src="{{ asset('images/icons/confined-icon.png') }}" alt="Confined spaces icon">
                    </span>
                    <img class="course-img" src="{{ asset('images/courses/enter-confined-space.jpg') }}" alt="Enter And Work In Confined Spaces">
                </div>
                <div class="card-body">
                    <div class="course-title">Enter And Work In Confined Spaces</div>
                </div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$110.00</span>
                </div>
            </div>

            {{-- Card 3 --}}
            <div class="course-card">
                <div class="card-image-wrap">
                    <span class="card-icon">
                        <img src="{{ asset('images/icons/gas-icon.png') }}" alt="Gas test icon">
                    </span>
                    <img class="course-img" src="{{ asset('images/courses/gas-test-atmospheres.jpg') }}" alt="Gas Test Atmospheres">
                </div>
                <div class="card-body">
                    <div class="course-title">Gas Test Atmospheres</div>
                </div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$80.00</span>
                </div>
            </div>

            {{-- Card 4 --}}
            <div class="course-card">
                <div class="card-image-wrap">
                    <span class="card-icon">
                        <img src="{{ asset('images/icons/confined-icon.png') }}" alt="Confined space icon">
                    </span>
                    <img class="course-img" src="{{ asset('images/courses/confined-spaces.jpg') }}" alt="Enter Confined Space">
                </div>
                <div class="card-body">
                    <div class="course-title">Enter Confined Space</div>
                </div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$110.00</span>
                </div>
            </div>

        </div>
    </section>

    {{-- ============================================================= --}}
    {{-- SECTION 2: PACKAGE ONLINE REFRESHER COURSES                   --}}
    {{-- ============================================================= --}}
    <section class="courses-section">
        <h1 class="section-title">Package Online Refresher Courses</h1>

        <div class="package-grid">

            {{-- Package 1: Confined Spaces + Heights (2 segments) --}}
            <div class="package-card">
                <div class="package-image-wrap">
                    {{-- confined-space icon over the RIIWHS202E (top) segment --}}
                    <span class="pkg-icon" style="top: 10px;">
                        <img src="{{ asset('images/icons/confined-icon.png') }}" alt="Confined spaces icon">
                    </span>
                    {{-- heights icon over the RIIWHS204E (bottom) segment --}}
                    <span class="pkg-icon" style="top: 52%;">
                        <img src="{{ asset('images/icons/heights-icon.png') }}" alt="Heights icon">
                    </span>
                    <img class="package-img" src="{{ asset('images/courses/package-confined-heights.jpg') }}" alt="Enter & Work In Confined Spaces, Work Safely At Heights">
                </div>
                <div class="package-codes">RIIWHS202E &amp; RIIWHS204E</div>
                <div class="package-title">Enter &amp; Work In Confined Spaces Work Safely At Heights</div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$220.00</span>
                </div>
            </div>

            {{-- Package 2: Confined Spaces + Gas Test + Heights (3 segments) --}}
            <div class="package-card">
                <div class="package-image-wrap">
                    {{-- confined-space icon over RIIWHS202E (top) --}}
                    <span class="pkg-icon" style="top: 10px;">
                        <img src="{{ asset('images/icons/confined-icon.png') }}" alt="Confined spaces icon">
                    </span>
                    {{-- heights icon over RIIWHS204E (middle) --}}
                    <span class="pkg-icon" style="top: 36%;">
                        <img src="{{ asset('images/icons/heights-icon.png') }}" alt="Heights icon">
                    </span>
                    {{-- gas icon over MSMWHS217 (bottom) --}}
                    <span class="pkg-icon" style="top: 66%;">
                        <img src="{{ asset('images/icons/gas-icon.png') }}" alt="Gas test icon">
                    </span>
                    <img class="package-img" src="{{ asset('images/courses/package-confined-gas-heights.jpg') }}" alt="Enter And Work In Confined Spaces, Gas Test Atmospheres, Work Safely At Heights">
                </div>
                <div class="package-codes">RIIWHS202E, MSMWHS217 &amp; RIIWHS204E</div>
                <div class="package-title">Enter And Work In Confined Spaces, Gas Test Atmospheres, Work Safely At Heights</div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$300.00</span>
                </div>
            </div>

            {{-- Package 3: Confined Spaces + Gas Test (2 segments) --}}
            <div class="package-card">
                <div class="package-image-wrap">
                    {{-- confined-space icon over RIIWHS202E (top) --}}
                    <span class="pkg-icon" style="top: 10px;">
                        <img src="{{ asset('images/icons/confined-icon.png') }}" alt="Confined spaces icon">
                    </span>
                    {{-- gas icon over MSMWHS217 (bottom) --}}
                    <span class="pkg-icon" style="top: 52%;">
                        <img src="{{ asset('images/icons/gas-icon.png') }}" alt="Gas test icon">
                    </span>
                    <img class="package-img" src="{{ asset('images/courses/package-confined-gas.jpg') }}" alt="Enter And Work In Confined Spaces, Gas Test Atmospheres">
                </div>
                <div class="package-codes">RIIWHS202E &amp; MSMWHS217</div>
                <div class="package-title">Enter And Work In Confined Spaces &amp; Gas Test Atmospheres.</div>
                <div class="card-footer">
                    <a href="#" class="view-btn">View course</a>
                    <span class="price">$190.00</span>
                </div>
            </div>

        </div>
    </section>

</body>
</html>
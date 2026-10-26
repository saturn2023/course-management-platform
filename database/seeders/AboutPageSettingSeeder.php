<?php

namespace Database\Seeders;

use App\Models\AboutPageSetting;
use Illuminate\Database\Seeder;

class AboutPageSettingSeeder extends Seeder
{
    /**
     * The current live copy from amstraining.com.au/about/ (taken 26 Oct
     * 2026), reproduced verbatim apart from "a RTO" → "an RTO", with the
     * curly apostrophes restored.
     *
     * Only seeds when the row does not exist yet, so re-running it never
     * overwrites edits made in Filament. Headings, hero image and SEO fields are
     * left null on purpose: the model's defaults already match the live page.
     *
     *     php artisan db:seed --class=AboutPageSettingSeeder
     */
    public function run(): void
    {
        if (AboutPageSetting::query()->exists()) {
            return;
        }

        AboutPageSetting::query()->create([
            'content_body' => implode("\n", [
                '<p>AMS Training was established 13 January 2021.</p>',
                '<p>AMS Training was registered with ASQA on the 23 September 2021, registration number 45735.</p>',
                '<p>The idea to establish AMS Training as an RTO came from the idea that we could offer better products &amp; services to clients in the Northern Territory and Western Australia.</p>',
                '<p>Over the past decade we have visited numerous locations in the Northern Territory and Western Australia where a need for ongoing accredited and non-accredited training services are required as a one off and ongoing basis.</p>',
            ]),

            'trainers' => [
                [
                    'photo' => null,
                    'photo_alt' => null,
                    'name' => 'Ian Cole',
                    'bio' => '<p>Our Operations Manager Ian Cole has managed and established several RTO’s over the past decade, with a keen eye for Health, Safety Quality and Environmental compliance. Ian Cole has been a Northern Territory and Western Australian High Risk Work Licence Accredited Worksafe assessor for 10 years.</p>',
                ],
                [
                    'photo' => null,
                    'photo_alt' => null,
                    'name' => 'Graeme Richmond',
                    'bio' => '<p>Graeme Richmond has been working in the Civil Construction and Surface Extraction industry for over 30 years and brings this industry experience to our Cert 3 qualifications and standalone Units. Graeme Richmond is also a Northern Territory High Risk Work Licence Accredited Assessor.</p>',
                ],
            ],

            'mission_points' => [
                'To provide quality nationally accredited training and assessment services in a safe professional manner.',
                'Meeting all regulator obligations.',
                'AMS Training offer Fee for service delivery and assessment of both Nationally Accredited Training and Non-Accredited Training.',
                'AMS Training offer our products and service at our Training facility in Darwin and at client’s workplaces throughout the Northern Territory and Western Australia. We also offer Online Refresher training Australia wide.',
            ],

            'show_reviews' => false,
        ]);
    }
}

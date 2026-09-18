<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseDetailContentSeeder extends Seeder
{
    /**
     * TEST CONTENT for the course detail page, applied to the EXISTING
     * RIIWHS204E record — it updates that row, it never creates a second one.
     *
     * The copy is generic placeholder wording, not AMS's real policies. Replace
     * it at Admin → Courses once the genuine text is available; anything an
     * administrator has already written is left alone (see the skip below).
     *
     * Run with:
     *     php artisan db:seed --class=CourseDetailContentSeeder
     *
     * Deliberately NOT registered in DatabaseSeeder — this is a one-off for
     * getting the template on screen, not part of a normal deploy.
     */
    public function run(): void
    {
        $course = Course::query()->where('code', 'RIIWHS204E')->first();

        if (! $course) {
            $this->command?->warn('RIIWHS204E not found — nothing seeded.');

            return;
        }

        $values = [
            'slug' => 'work-safely-at-heights-online-refresher-riiwhs204e',
            'course_type' => 'Online Refresher Course',
            'duration' => '2 - 4 hours',
            'nationally_accredited' => true,
            'regular_price' => 140.00,

            'overview' => '<p>This Nationally Accredited Training course is designed for workers who need to refresh their <strong>Work Safely at Heights</strong> qualification. It covers the skills and knowledge required to work safely at heights across a range of workplace settings.</p>'
                .'<p>The course is delivered 100% online and can be completed at your own pace. On successful completion you receive a nationally recognised statement of attainment.</p>',

            'entry_requirements_warning' => '<p>PLEASE READ THE FOLLOWING ENTRY REQUIREMENTS. IF YOU DO NOT MEET THE ENTRY REQUIREMENTS PLEASE DO NOT ENROL AS FEES WILL APPLY.</p>',

            'entry_requirements' => '<p>To enrol in this refresher course you must:</p>'
                .'<ul>'
                .'<li>Hold a current or recently expired statement of attainment for RIIWHS204E</li>'
                .'<li>Be at least 18 years of age</li>'
                .'<li>Have access to a computer or tablet with a reliable internet connection</li>'
                .'<li>Have a <a href="https://www.usi.gov.au">Unique Student Identifier (USI)</a></li>'
                .'</ul>',

            'course_information' => '<p>Placeholder course information &mdash; replace from Admin &rarr; Courses.</p>',
            'assessment' => '<p>Assessment is completed online and consists of a knowledge quiz and a short practical verification.</p>',
            'qualification' => '<p>On successful completion you receive a Statement of Attainment for <strong>RIIWHS204E Work Safely at Heights</strong>.</p>',
            'equipment_materials' => '<p>All learning materials are provided online. You will need a device with internet access.</p>',
            'delivery_mode' => '<p>100% online, self-paced.</p>',
            'support_information' => '<p>Student support is available by phone and email during business hours.</p>',
            'cancellations_refunds' => '<p>Placeholder cancellations and refunds policy &mdash; replace from Admin &rarr; Courses.</p>',
        ];

        // Only fill what is still empty, so re-running cannot overwrite real
        // copy someone has since entered in admin. The slug is refreshed
        // regardless, since the old value was a "test-" placeholder.
        foreach ($values as $field => $value) {
            if ($field === 'slug' || blank($course->{$field})) {
                $course->{$field} = $value;
            }
        }

        $course->save();

        $this->command?->info('RIIWHS204E updated. URL: /courses/'.$course->slug);
    }
}

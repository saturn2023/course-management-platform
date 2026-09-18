<?php

namespace Database\Seeders;

use App\Models\EnrolmentStep;
use Illuminate\Database\Seeder;

class EnrolmentStepSeeder extends Seeder
{
    /**
     * The five steps from the reference design.
     *
     * Only the titles are real — no body copy for these steps exists anywhere in
     * the project, and inventing AMS enrolment procedure would put made-up
     * business content on a live page. Each body is therefore an obvious
     * placeholder, to be replaced at Admin → Enrolment Steps.
     *
     * Idempotent: keyed on title, so re-running this seeder updates the five
     * rows rather than duplicating them, and never overwrites body copy an
     * administrator has since edited (see the two-stage updateOrCreate below).
     */
    public function run(): void
    {
        $placeholder = 'Temporary placeholder content — replace from Filament.';

        $steps = [
            'Step 1. Select required course.',
            'Step 2. Complete registration and make payment.',
            'Step 3. Confirmation',
            'Step 4. Evidence Validation',
            'Step 5. Student Login',
        ];

        foreach ($steps as $index => $title) {
            $step = EnrolmentStep::firstOrNew(['title' => $title]);

            // Order and active state are always brought back in line with the
            // reference design; content is only filled on first creation so a
            // re-run cannot wipe copy written in admin.
            $step->display_order = $index + 1;
            $step->is_active = true;

            if (! $step->exists) {
                $step->content = $placeholder;
            }

            $step->save();
        }
    }
}

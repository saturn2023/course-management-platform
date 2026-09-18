<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields for the public course detail page (/courses/{slug}).
     *
     * All additive and all nullable, so the four existing course records keep
     * working the moment this runs — the detail page simply omits whatever has
     * not been filled in yet.
     *
     * Deliberately NOT touched:
     *  - `price`   stays the payable/sale amount. OrderForm and the checkout
     *              already read it; `regular_price` is the new struck-through
     *              "was" figure beside it. The discount percentage is derived
     *              from the pair rather than stored, so the two can never
     *              disagree.
     *  - `slug`    already exists.
     *  - `description` stays the short admin/card copy; `overview` is the
     *              multi-paragraph detail-page version.
     *  - `course_url` stays the homepage card's link target.
     *
     * The long fields are rich text from Filament's editor, hence longText.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Header / meta row.
            $table->string('course_type')->nullable()->after('description');
            $table->string('duration')->nullable()->after('course_type');
            $table->boolean('nationally_accredited')->default(true)->after('duration');

            // Pricing. `price` remains the sale/payable amount.
            $table->decimal('regular_price', 8, 2)->nullable()->after('price');

            // Enrolment. Optional override for the /checkout/load link that is
            // otherwise built from ams_enrolment_code + ams_plan_id.
            $table->string('enrolment_url')->nullable()->after('regular_price');

            // Detail page body content.
            $table->longText('overview')->nullable();
            $table->longText('entry_requirements_warning')->nullable();
            $table->longText('entry_requirements')->nullable();
            $table->longText('course_information')->nullable();
            $table->longText('assessment')->nullable();
            $table->longText('qualification')->nullable();
            $table->longText('equipment_materials')->nullable();
            $table->longText('delivery_mode')->nullable();
            $table->longText('support_information')->nullable();
            $table->longText('student_handbook')->nullable();
            $table->longText('vet_transcript')->nullable();
            $table->longText('usi_information')->nullable();
            $table->longText('complaints_appeals')->nullable();
            $table->longText('cancellations_refunds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'course_type',
                'duration',
                'nationally_accredited',
                'regular_price',
                'enrolment_url',
                'overview',
                'entry_requirements_warning',
                'entry_requirements',
                'course_information',
                'assessment',
                'qualification',
                'equipment_materials',
                'delivery_mode',
                'support_information',
                'student_handbook',
                'vet_transcript',
                'usi_information',
                'complaints_appeals',
                'cancellations_refunds',
            ]);
        });
    }
};

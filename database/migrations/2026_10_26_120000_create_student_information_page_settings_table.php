<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table for the /student-information page — same
     * pattern as about_page_settings: one row, one column per editable field,
     * prefixed with the section it belongs to. See
     * App\Models\StudentInformationPageSetting.
     *
     * The documents list is its own table (student_documents) rather than a
     * JSON column here: each item has an uploaded file with its own public
     * download URL, so it needs a stable row and slug.
     */
    public function up(): void
    {
        Schema::create('student_information_page_settings', function (Blueprint $table) {
            $table->id();

            // HERO BANNER
            $table->string('hero_image')->nullable();
            $table->string('hero_image_alt')->nullable();
            $table->string('hero_title')->nullable();

            // INTRO — intro_text is plain text; the view escapes it and keeps
            // the line breaks.
            $table->string('intro_heading')->nullable();
            $table->text('intro_text')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_information_page_settings');
    }
};

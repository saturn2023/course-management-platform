<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accordion steps for the homepage "What is the enrolment process?" section,
     * managed at Admin → Enrolment Steps.
     *
     * display_order (not sort_order) matches the column already used by courses
     * and course_packages, so the Filament tables all sort the same way.
     */
    public function up(): void
    {
        Schema::create('enrolment_steps', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolment_steps');
    }
};

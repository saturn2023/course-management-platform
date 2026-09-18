<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer reviews for the homepage carousel, entered by hand at
     * Admin → Reviews. Nothing here talks to Google — `source` is just a label
     * the admin types, so a review from anywhere can be recorded truthfully.
     *
     * display_order matches courses, course_packages and enrolment_steps.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reviewer_name');
            $table->date('review_date')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('review_text');
            $table->string('reviewer_avatar')->nullable();
            $table->string('source')->nullable()->default('Google');
            $table->string('source_url')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

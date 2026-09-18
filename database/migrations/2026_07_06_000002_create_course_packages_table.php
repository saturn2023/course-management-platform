<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standalone "Package Online Refresher" cards for the homepage. Each package
     * has its own 1–3 stacked images + matching icons, a combined codes line,
     * a two-line title and a link. Independent of the single Course records.
     */
    public function up(): void
    {
        Schema::create('course_packages', function (Blueprint $table) {
            $table->id();

            // Two-line card title (may contain a newline for the second line).
            $table->string('title');

            // Combined course codes, e.g. "RIIWHS202E & RIIWHS204E".
            $table->string('codes')->nullable();

            $table->string('banner_text')->nullable()->default('PACKAGE ONLINE REFRESHER');
            $table->string('course_url')->nullable();

            // Up to three stacked images, each with an optional overlay icon.
            $table->string('image_1')->nullable();
            $table->string('icon_1')->nullable();
            $table->string('image_2')->nullable();
            $table->string('icon_2')->nullable();
            $table->string('image_3')->nullable();
            $table->string('icon_3')->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('show_on_homepage')->default(true);
            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_packages');
    }
};

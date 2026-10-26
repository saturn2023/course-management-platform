<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table for the /about page — same pattern as
     * homepage_settings: one row, one column per editable field, prefixed with
     * the section it belongs to. See App\Models\AboutPageSetting.
     *
     * The two repeaters (trainers, mission points) are JSON columns on this row
     * rather than child tables: they only ever belong to this one page, and it
     * keeps the whole page saving from a single form. Order is the array
     * order, set by dragging in Filament.
     */
    public function up(): void
    {
        Schema::create('about_page_settings', function (Blueprint $table) {
            $table->id();

            // HERO BANNER
            $table->string('hero_image')->nullable();
            $table->string('hero_image_alt')->nullable();
            $table->string('hero_title')->nullable();

            // ABOUT CONTENT
            $table->string('content_heading')->nullable();
            $table->longText('content_body')->nullable();

            // TRAINERS — [{photo, photo_alt, name, bio}, ...]
            $table->string('trainers_heading')->nullable();
            $table->json('trainers')->nullable();

            // MISSION STATEMENT — ["point", ...]
            $table->string('mission_heading')->nullable();
            $table->json('mission_points')->nullable();

            // REVIEWS (the existing <x-home.reviews /> carousel)
            $table->boolean('show_reviews')->default(false);

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_page_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table for editable homepage content that is NOT a
     * repeatable record (course cards / packages already have their own tables).
     *
     * One row, one column per editable field, prefixed with the section it
     * belongs to (about_*). Future homepage sections add their own prefixed
     * columns here rather than each getting a table + Filament resource of
     * their own — see App\Models\HomepageSetting.
     */
    public function up(): void
    {
        Schema::create('homepage_settings', function (Blueprint $table) {
            $table->id();

            // ABOUT BANNER (below the Package Online Refresher section).
            $table->string('about_background_image')->nullable();
            $table->string('about_logo')->nullable();
            $table->text('about_heading')->nullable();
            $table->string('about_button_text')->nullable();
            $table->string('about_button_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_settings');
    }
};

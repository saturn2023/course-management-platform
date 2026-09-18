<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FIRST IN OUR FIELD SECTION (below the Experience section).
     *
     * The mirror image of the Experience section — photograph left, card right —
     * so it takes the same six fields with a first_field_ prefix, on the same
     * single homepage_settings row. All nullable; App\Models\HomepageSetting
     * supplies a default for each so the section renders before it is configured.
     */
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->string('first_field_background_image')->nullable()->after('experience_button_url');
            $table->string('first_field_background_image_mobile')->nullable()->after('first_field_background_image');
            $table->text('first_field_heading')->nullable()->after('first_field_background_image_mobile');
            $table->text('first_field_description')->nullable()->after('first_field_heading');
            $table->string('first_field_button_text')->nullable()->after('first_field_description');
            $table->string('first_field_button_url')->nullable()->after('first_field_button_text');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'first_field_background_image',
                'first_field_background_image_mobile',
                'first_field_heading',
                'first_field_description',
                'first_field_button_text',
                'first_field_button_url',
            ]);
        });
    }
};

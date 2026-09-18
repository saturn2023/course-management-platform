<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EXPERIENCE SECTION (below the About banner on the homepage).
     *
     * Follows the pattern set by the create migration: a new homepage section
     * adds its own prefixed columns to the single homepage_settings row rather
     * than getting a table and resource of its own. Every column is nullable —
     * App\Models\HomepageSetting supplies a default for each, so the section
     * renders correctly before anything has been configured.
     */
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->string('experience_background_image')->nullable()->after('about_button_url');
            $table->text('experience_heading')->nullable()->after('experience_background_image');
            $table->text('experience_description')->nullable()->after('experience_heading');
            $table->string('experience_button_text')->nullable()->after('experience_description');
            $table->string('experience_button_url')->nullable()->after('experience_button_text');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn([
                'experience_background_image',
                'experience_heading',
                'experience_description',
                'experience_button_text',
                'experience_button_url',
            ]);
        });
    }
};

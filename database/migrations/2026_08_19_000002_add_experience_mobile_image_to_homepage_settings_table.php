<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A phone-sized crop of the Experience photograph.
     *
     * The desktop shot is a wide landscape frame whose left third is near-empty
     * sky — fine behind the white card at desktop, but it dominates the picture
     * once the photo becomes its own row on a narrow screen. This column holds
     * an optional tighter crop used only at phone widths; when it is empty the
     * section falls back to experience_background_image, so the field is purely
     * additive and nothing breaks if it is never filled.
     */
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->string('experience_background_image_mobile')
                ->nullable()
                ->after('experience_background_image');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            $table->dropColumn('experience_background_image_mobile');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only — frontend homepage card fields. No existing Course
     * columns are touched or removed.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('status');
            $table->string('icon_path')->nullable()->after('image_path');
            $table->string('banner_text')->nullable()->default('100% ONLINE REFRESHER')->after('icon_path');
            $table->string('course_url')->nullable()->after('banner_text');
            $table->unsignedInteger('display_order')->default(0)->after('course_url');
            $table->boolean('show_on_homepage')->default(true)->after('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'image_path',
                'icon_path',
                'banner_text',
                'course_url',
                'display_order',
                'show_on_homepage',
            ]);
        });
    }
};

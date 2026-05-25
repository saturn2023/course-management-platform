<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('ams_enrolment_code')->nullable()->after('code');
            $table->string('ams_plan_id')->nullable()->after('ams_enrolment_code');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'ams_enrolment_code',
                'ams_plan_id',
            ]);
        });
    }
};
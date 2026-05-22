<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('enrolment_token', 80)->nullable()->unique()->after('enrolment_link');
            $table->timestamp('enrolment_token_expires_at')->nullable()->after('enrolment_token');
            $table->timestamp('enrolment_completed_at')->nullable()->after('enrolment_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn([
                'enrolment_token',
                'enrolment_token_expires_at',
                'enrolment_completed_at',
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolment_submissions', function (Blueprint $table) {
            $table->string('api_status')->default('pending')->after('submitted_at');
            $table->unsignedInteger('api_attempts')->default(0)->after('api_status');
            $table->timestamp('api_last_attempted_at')->nullable()->after('api_attempts');
            $table->timestamp('api_submitted_at')->nullable()->after('api_last_attempted_at');
            $table->text('api_error_message')->nullable()->after('api_submitted_at');
            $table->json('api_request_payload')->nullable()->after('api_error_message');
            $table->json('api_response_payload')->nullable()->after('api_request_payload');
            $table->string('external_reference')->nullable()->after('api_response_payload');
        });
    }

    public function down(): void
    {
        Schema::table('enrolment_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'api_status',
                'api_attempts',
                'api_last_attempted_at',
                'api_submitted_at',
                'api_error_message',
                'api_request_payload',
                'api_response_payload',
                'external_reference',
            ]);
        });
    }
};
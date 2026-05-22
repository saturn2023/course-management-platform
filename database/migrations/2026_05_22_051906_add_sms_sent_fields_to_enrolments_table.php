<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->timestamp('sms_sent_at')->nullable()->after('link_sent_at');
            $table->text('sms_error_message')->nullable()->after('sms_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn([
                'sms_sent_at',
                'sms_error_message',
            ]);
        });
    }
};
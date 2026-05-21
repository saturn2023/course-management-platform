<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('status');
            $table->timestamp('link_sent_at')->nullable()->after('email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn([
                'email_sent_at',
                'link_sent_at',
            ]);
        });
    }
};
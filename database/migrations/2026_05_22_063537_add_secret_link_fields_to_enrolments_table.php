<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->string('secret_key', 120)->nullable()->after('enrolment_link');
            $table->text('secret_base_url')->nullable()->after('secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn([
                'secret_key',
                'secret_base_url',
            ]);
        });
    }
};
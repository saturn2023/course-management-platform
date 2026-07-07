<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only. Optional homepage-card heading that may be split across
     * two lines. Falls back to the canonical `title` when empty, so orders,
     * Xero invoices and enrolments continue to use `title` unchanged.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('card_title')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('card_title');
        });
    }
};

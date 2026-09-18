<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'origin')) {
                /*
                |----------------------------------------------------------------
                | First-touch marketing attribution
                |----------------------------------------------------------------
                |
                | Plain nullable string rather than an enum so new sources
                | (Bing Ads, Meta Ads, Referral, ...) can be added without a
                | schema change. Captured values are the human-readable labels
                | listed in App\Support\OrderAttribution.
                |
                | NULL means "unknown" - orders created before attribution
                | tracking existed keep NULL and must never be shown as Direct.
                |
                */
                $table->string('origin')->nullable()->after('status');

                $table->index('origin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'origin')) {
                $table->dropIndex(['origin']);
                $table->dropColumn('origin');
            }
        });
    }
};

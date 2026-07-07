<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Stamped only after the official Xero invoice PDF has been
            // emailed successfully. Acts as the duplicate-send guard.
            $table->timestamp('invoice_sent_at')->nullable()->after('xero_error_message');

            // sent | failed
            $table->string('invoice_email_status')->nullable()->after('invoice_sent_at');

            $table->text('invoice_email_error')->nullable()->after('invoice_email_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_sent_at',
                'invoice_email_status',
                'invoice_email_error',
            ]);
        });
    }
};

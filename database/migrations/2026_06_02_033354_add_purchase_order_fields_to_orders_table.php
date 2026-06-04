<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchase_order_number')->nullable()->after('billing_abn');
            $table->string('purchase_order_document_path')->nullable()->after('purchase_order_number');
            $table->string('payment_method')->nullable()->after('purchase_order_document_path');
            $table->string('payment_status')->default('pending')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_order_number',
                'purchase_order_document_path',
                'payment_method',
                'payment_status',
            ]);
        });
    }
};
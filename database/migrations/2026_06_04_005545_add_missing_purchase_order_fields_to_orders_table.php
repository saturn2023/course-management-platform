<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'purchase_order_number')) {
                $table->string('purchase_order_number')->nullable()->after('billing_abn');
            }

            if (! Schema::hasColumn('orders', 'purchase_order_document_path')) {
                $table->string('purchase_order_document_path')->nullable()->after('purchase_order_number');
            }

            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('purchase_order_document_path');
            }

            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'purchase_order_number',
                'purchase_order_document_path',
                'payment_method',
                'payment_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
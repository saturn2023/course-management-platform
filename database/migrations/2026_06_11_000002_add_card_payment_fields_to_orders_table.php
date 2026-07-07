<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /*
            | Card-payment fields. payment_method / payment_status already
            | exist from the purchase-order migrations and are reused.
            |
            | pin_charge_token is unique so a single Pin charge can never be
            | attached to two orders. Only the masked card display number and
            | scheme are stored; raw card data never reaches Laravel.
            */
            $table->string('pin_charge_token')->nullable()->unique()->after('payment_status');
            $table->unsignedInteger('pin_charge_amount_cents')->nullable()->after('pin_charge_token');
            $table->string('card_scheme')->nullable()->after('pin_charge_amount_cents');
            $table->string('card_display_number')->nullable()->after('card_scheme');
            $table->timestamp('paid_at')->nullable()->after('card_display_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['pin_charge_token']);
            $table->dropColumn([
                'pin_charge_token',
                'pin_charge_amount_cents',
                'card_scheme',
                'card_display_number',
                'paid_at',
            ]);
        });
    }
};

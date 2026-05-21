<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_first_name')->nullable()->after('student_id');
            $table->string('billing_last_name')->nullable()->after('billing_first_name');
            $table->string('billing_company')->nullable()->after('billing_last_name');
            $table->string('billing_email')->nullable()->after('billing_company');
            $table->string('billing_phone')->nullable()->after('billing_email');
            $table->string('billing_address_1')->nullable()->after('billing_phone');
            $table->string('billing_address_2')->nullable()->after('billing_address_1');
            $table->string('billing_city')->nullable()->after('billing_address_2');
            $table->string('billing_postcode')->nullable()->after('billing_city');
            $table->string('billing_abn')->nullable()->after('billing_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'billing_first_name',
                'billing_last_name',
                'billing_company',
                'billing_email',
                'billing_phone',
                'billing_address_1',
                'billing_address_2',
                'billing_city',
                'billing_postcode',
                'billing_abn',
            ]);
        });
    }
};
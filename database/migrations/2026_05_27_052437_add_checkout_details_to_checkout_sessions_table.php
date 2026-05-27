<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->json('student_details')->nullable()->after('rto_payload');
            $table->json('billing_details')->nullable()->after('student_details');
            $table->timestamp('details_completed_at')->nullable()->after('billing_details');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'student_details',
                'billing_details',
                'details_completed_at',
            ]);
        });
    }
};
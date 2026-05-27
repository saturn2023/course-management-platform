<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Inputs from Enrol Now link
            $table->string('course_code');
            $table->string('plan_id');
            $table->string('schedule_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            // Snapshot from RTO Data /detail
            $table->string('course_title')->nullable();
            $table->string('plan_title')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('dates')->nullable();
            $table->string('delivery_mode')->nullable();

            // Pricing locked at load time from RTO Data
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);

            // Capacity snapshot
            $table->integer('stock_quantity')->nullable();
            $table->integer('enrolments')->nullable();

            // Full RTO Data /detail response snapshot
            $table->json('rto_payload')->nullable();

            // Lifecycle
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index('expires_at');
            $table->index(['course_code', 'plan_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
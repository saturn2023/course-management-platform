<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Relationships
            |------------------------------------------------------------------
            |
            | checkout_session_id is the required owning session. It is
            | restricted on delete so reconciliation-grade payment records can
            | never be silently destroyed by removing a checkout session.
            |
            */
            $table->foreignId('checkout_session_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |------------------------------------------------------------------
            | MySQL-compatible duplicate-payment guards
            |------------------------------------------------------------------
            |
            | MySQL has no partial unique index, but a UNIQUE index permits
            | many NULLs while allowing only one non-NULL value. We exploit
            | that:
            |
            |  - inflight_session_id holds the checkout_session_id only while
            |    the attempt is in a non-terminal state (charging /
            |    three_d_secure_pending) and is cleared on every terminal
            |    state. UNIQUE => at most one in-flight attempt per session.
            |
            |  - paid_session_id holds the checkout_session_id once the attempt
            |    is conclusively paid and is NEVER cleared (kept for both paid
            |    and paid_order_failed). UNIQUE => at most one successful
            |    payment per session, forever.
            |
            */
            $table->foreignId('inflight_session_id')
                ->nullable()
                ->unique()
                ->constrained('checkout_sessions')
                ->nullOnDelete();

            $table->foreignId('paid_session_id')
                ->nullable()
                ->unique()
                ->constrained('checkout_sessions')
                ->nullOnDelete();

            /*
            |------------------------------------------------------------------
            | Pin references (durable). card_token is intentionally NOT stored.
            |------------------------------------------------------------------
            */
            $table->string('pin_charge_token')->nullable()->unique();
            $table->string('pin_session_token')->nullable();

            /*
            |------------------------------------------------------------------
            | State machine
            |------------------------------------------------------------------
            | charging | three_d_secure_pending | paid | paid_order_failed
            | declined | failed
            */
            $table->string('state')->default('charging');

            /*
            |------------------------------------------------------------------
            | Amount (server-derived from CheckoutSession subtotal, in cents)
            |------------------------------------------------------------------
            |
            | Required and non-null: every attempt records its expected,
            | server-derived amount at claim time, so a charge can never be
            | made or reconciled without a known expected amount.
            |
            */
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('AUD');

            $table->boolean('success')->default(false);

            /*
            |------------------------------------------------------------------
            | 3D Secure state
            |------------------------------------------------------------------
            */
            $table->boolean('three_d_secure_required')->default(false);
            $table->text('redirect_url')->nullable();
            $table->timestamp('verified_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Result / reconciliation
            |------------------------------------------------------------------
            */
            $table->string('error_code')->nullable();
            $table->string('status_message')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Denormalised for admin search (guest orders have no user_id)
            |------------------------------------------------------------------
            */
            $table->string('billing_email')->nullable();
            $table->string('checkout_session_uuid')->nullable();

            $table->timestamps();

            $table->index('state');
            $table->index('billing_email');
            $table->index('checkout_session_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Relationships
            |------------------------------------------------------------------
            |
            | order_id is restricted on delete for the same reason as
            | payment_attempts: financial records must never be silently
            | destroyed by removing the order they belong to.
            |
            | created_by is the admin User who recorded the refund. Nullable so
            | removing a staff account never deletes the refund record.
            |
            */
            $table->foreignId('order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |------------------------------------------------------------------
            | Amount
            |------------------------------------------------------------------
            |
            | Same decimal(10, 2) shape as orders.subtotal / orders.total so no
            | floating point drift is introduced. The original order total is
            | never modified; the remaining refundable balance is derived as
            | order total - sum(balance-affecting refunds).
            |
            */
            $table->decimal('amount', 10, 2);

            $table->string('reason');
            $table->text('notes')->nullable();

            /*
            |------------------------------------------------------------------
            | Status
            |------------------------------------------------------------------
            | recorded  - logged in the admin only, no money moved (default)
            | pending   - submitted to a provider, awaiting confirmation
            | processed - money genuinely returned by the payment provider
            | failed    - did not complete; releases the refundable balance
            */
            $table->string('status')->default('recorded');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};

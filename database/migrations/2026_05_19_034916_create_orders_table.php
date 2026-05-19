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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
$table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
$table->decimal('subtotal', 10, 2)->default(0);
$table->decimal('total', 10, 2)->default(0);
$table->string('status')->default('pending'); 
// pending, paid, processing, completed, failed

$table->string('xero_status')->default('pending');
// pending, processing, success, failed

$table->string('enrolment_status')->default('pending');
// pending, processing, success, failed

$table->string('xero_invoice_id')->nullable();
$table->string('xero_invoice_number')->nullable();
$table->text('xero_error_message')->nullable();

$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

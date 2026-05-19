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
        Schema::create('integration_logs', function (Blueprint $table) {
           $table->id();
$table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
$table->string('service'); 
// xero, enrolment_api, email, sms

$table->string('action')->nullable(); 
// create_invoice, create_enrolment, send_email, send_sms

$table->string('status')->default('pending');
// pending, processing, success, failed

$table->longText('request_payload')->nullable();
$table->longText('response_payload')->nullable();
$table->text('error_message')->nullable();

$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};

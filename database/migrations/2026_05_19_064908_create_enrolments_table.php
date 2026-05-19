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
        Schema::create('enrolments', function (Blueprint $table) {
           $table->id();

$table->foreignId('order_id')->constrained()->cascadeOnDelete();
$table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();

$table->string('external_enrolment_id')->nullable();
$table->string('enrolment_link')->nullable();

$table->string('status')->default('pending');
// pending, processing, success, failed

$table->text('error_message')->nullable();

$table->longText('request_payload')->nullable();
$table->longText('response_payload')->nullable();

$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};

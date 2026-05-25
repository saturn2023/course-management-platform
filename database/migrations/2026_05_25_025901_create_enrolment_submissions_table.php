<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolment_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('enrolment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('student_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('course_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('code')->nullable();
            $table->string('plan')->nullable();

            // Store the full form as JSON for now.
            // This is flexible while the form is still changing.
            $table->json('form_data')->nullable();

            // File upload paths.
            $table->string('id_document_path')->nullable();
            $table->string('vet_transcript_path')->nullable();

            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->unique('enrolment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolment_submissions');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The download links on /student-information. Each row is either an
     * uploaded file (file_path on the private `local` disk, served through
     * the student-documents.download route) or an external link (url) —
     * never both. The admin form enforces that. See App\Models\StudentDocument.
     */
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // The public download URL — must not change when a file is replaced.
            $table->string('slug')->unique();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};

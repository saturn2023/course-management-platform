<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrolment v2 tables.
 *
 * Port of the two tables in reference/wp-ams2.php (`ams2_table()` and
 * `ams2_docs_table()`, created around L374-416), plus the three columns added
 * later by the ALTER at L2756 that the original CREATE TABLE does not show.
 *
 * Deliberately separate from the existing `enrolments` /
 * `enrolment_submissions` tables: v2 runs alongside the old flow, and only
 * courses with a published Enrolment Email template use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams2_enrolments', function (Blueprint $table) {
            $table->id();

            // One v2 row per enrolment link.
            $table->string('secret_key', 128)->unique();

            $table->unsignedBigInteger('order_id')->default(0);
            $table->integer('course_id')->default(0);
            $table->integer('plan_id')->default(0);
            $table->string('course_code', 64)->default('');
            $table->string('course_title')->default('');

            // The submitted form, as posted.
            $table->json('student_data');

            /*
             * The learner's real address, held here while RTO Data carries the
             * placeholder. The alert quotes this so a stuck enrolment can be
             * repaired by hand — a returning learner's RTO Data record may
             * still hold 'processing{id}hold.invalid'.
             */
            $table->string('real_email')->default('');

            $table->string('docs_expected')->default('');
            $table->boolean('docs_final')->default(false);

            // Assigned by RTO Data once the enrol call succeeds.
            $table->string('enrolment_id', 32)->nullable();
            $table->string('learner_id', 32)->nullable();

            $table->string('status', 32)->default('received')->index();

            // Where a needs_review row resumes from.
            $table->string('resume_status', 32)->default('');

            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamps();
            $table->timestamp('email_sent_at')->nullable();

            /*
             * The verify path: when an enrol call gives no clear answer the row
             * parks here and RTO Data is checked before anything is retried.
             */
            $table->timestamp('verify_after')->nullable();
            $table->integer('verify_checks')->default(0);
            $table->integer('reenrol_count')->default(0);
        });

        Schema::create('ams2_enrolment_documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('enrolment_row')->index();
            $table->string('doc_type', 32);
            $table->string('file_path', 500);

            /*
             * The filename the learner uploaded. RTO Data must receive THIS,
             * not the randomised storage name, so it is captured at upload time
             * even though nothing sends documents yet.
             */
            $table->string('original_name')->default('');

            $table->string('mime', 100)->default('');
            $table->unsignedBigInteger('size_bytes')->default(0);

            // pending | retry | sending | sent | failed | replaced
            $table->string('status', 20)->default('pending')->index();

            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamps();
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams2_enrolment_documents');
        Schema::dropIfExists('ams2_enrolments');
    }
};

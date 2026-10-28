<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-course enrolment confirmation email templates.
 *
 * Port of the WordPress `ams_enrol_email` custom post type (see
 * reference/wp-ams2.php). A published row for a course/plan pair is also the
 * gate that decides whether that pair uses the Enrolment v2 flow at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolment_email_templates', function (Blueprint $table) {
            $table->id();

            /*
             * RTO Data's own course_id / plan_id, not foreign keys to courses.id.
             * WordPress matches the enrolment row's course_id and plan_id directly,
             * and the local equivalents (Course.ams_enrolment_code / ams_plan_id)
             * are nullable and editable — a foreign key here would quietly drift
             * away from what the lookup actually compares.
             */
            $table->unsignedInteger('course_id');
            $table->unsignedInteger('plan_id');

            // WordPress post_status = publish.
            $table->boolean('is_published')->default(false);

            // WordPress post title: an admin-facing label, never sent.
            $table->string('name');

            // Raw HTML. Placeholders: {first_name} {last_name} {course_title}
            $table->longText('email_body');

            /*
             * Up to three optional attachments, stored on the private local disk
             * because they are mail attachments and must never be publicly
             * reachable. A missing file is skipped at send time.
             */
            $table->string('attachment_1_path')->nullable();
            $table->string('attachment_2_path')->nullable();
            $table->string('attachment_3_path')->nullable();

            /*
             * Stable per-template directory key. Attachments keep their original
             * filenames, so each template needs its own folder or two templates
             * uploading "Learner Handbook.pdf" would overwrite each other.
             */
            $table->string('storage_key', 40);

            $table->timestamps();

            /*
             * Supports the publish gate lookup. Deliberately not unique: only one
             * PUBLISHED row per pair is allowed, and MySQL cannot express a
             * partial unique index, so that rule is enforced in the model.
             */
            $table->index(['course_id', 'plan_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolment_email_templates');
    }
};

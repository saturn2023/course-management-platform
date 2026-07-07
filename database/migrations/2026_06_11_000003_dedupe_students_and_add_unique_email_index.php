<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merge duplicate students (matched by normalised email), reassigning all
     * relationships to a single canonical student, then enforce uniqueness.
     *
     * No enrolments or order relationships are deleted — they are moved to the
     * canonical student. Only the duplicate Student rows themselves are removed.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // Group every student by normalised email (lowercased + trimmed).
            $groups = [];

            foreach (DB::table('students')->select('id', 'email')->orderBy('id')->get() as $student) {
                $normalised = strtolower(trim((string) $student->email));
                $groups[$normalised][] = $student->id;
            }

            foreach ($groups as $normalisedEmail => $ids) {
                // Lowest id is canonical; the rest are duplicates to merge in.
                $canonicalId = $ids[0];
                $duplicateIds = array_slice($ids, 1);

                foreach ($duplicateIds as $duplicateId) {
                    $this->moveOrderStudents($duplicateId, $canonicalId);

                    // Tables with a plain student_id reference: reassign, never delete.
                    DB::table('enrolments')->where('student_id', $duplicateId)->update(['student_id' => $canonicalId]);
                    DB::table('enrolment_submissions')->where('student_id', $duplicateId)->update(['student_id' => $canonicalId]);
                    DB::table('orders')->where('student_id', $duplicateId)->update(['student_id' => $canonicalId]);

                    DB::table('students')->where('id', $duplicateId)->delete();
                }

                // Normalise the canonical email so future updateOrCreate() matches
                // and the unique index is consistent.
                DB::table('students')->where('id', $canonicalId)->update(['email' => $normalisedEmail]);
            }
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        // The merge cannot be reversed; only the constraint is dropped.
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }

    /**
     * Move a duplicate student's order_students pivot rows to the canonical
     * student, skipping any that would violate the (order_id, student_id)
     * unique constraint because the canonical student is already attached.
     */
    private function moveOrderStudents(int $duplicateId, int $canonicalId): void
    {
        $pivots = DB::table('order_students')->where('student_id', $duplicateId)->get();

        foreach ($pivots as $pivot) {
            $canonicalAlreadyAttached = DB::table('order_students')
                ->where('order_id', $pivot->order_id)
                ->where('student_id', $canonicalId)
                ->exists();

            if ($canonicalAlreadyAttached) {
                // Canonical student is already on this order: drop the duplicate
                // pivot row to avoid a duplicate (order_id, student_id) pair.
                DB::table('order_students')->where('id', $pivot->id)->delete();
            } else {
                DB::table('order_students')->where('id', $pivot->id)->update(['student_id' => $canonicalId]);
            }
        }
    }
};

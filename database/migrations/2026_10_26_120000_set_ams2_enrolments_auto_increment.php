<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Start ams2_enrolments ids at 100000.
 *
 * The id is visible to operators as the "AMS2-{id}" reference and is baked into
 * the RTO Data placeholder email ('processing{id}hold.invalid'). Starting high
 * keeps those from colliding with, or being mistaken for, the small ids that
 * every other table hands out.
 *
 * Rows that already exist keep their ids; this only moves the next one.
 */
return new class extends Migration
{
    private const FLOOR = 100000;

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            /*
             * MySQL refuses to lower AUTO_INCREMENT below the current maximum,
             * so this is a no-op once the table has grown past the floor.
             */
            DB::statement('ALTER TABLE ams2_enrolments AUTO_INCREMENT = '.self::FLOOR);

            return;
        }

        if ($driver === 'sqlite') {
            /*
             * SQLite keeps the counter in sqlite_sequence, and the row only
             * exists after the first insert. "seq" is the LAST id used, so the
             * next one is seq + 1.
             */
            $next = self::FLOOR - 1;

            $current = (int) DB::table('sqlite_sequence')
                ->where('name', 'ams2_enrolments')
                ->value('seq');

            if ($current >= $next) {
                return;
            }

            if ($current === 0) {
                DB::table('sqlite_sequence')->insert([
                    'name' => 'ams2_enrolments',
                    'seq' => $next,
                ]);

                return;
            }

            DB::table('sqlite_sequence')
                ->where('name', 'ams2_enrolments')
                ->update(['seq' => $next]);
        }
    }

    public function down(): void
    {
        // Lowering the counter again could hand out an id that is already taken.
    }
};

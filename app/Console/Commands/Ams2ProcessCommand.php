<?php

namespace App\Console\Commands;

use App\Enrolment\V2\Ams2DryRun;
use App\Enrolment\V2\Ams2Processor;
use Illuminate\Console\Command;

class Ams2ProcessCommand extends Command
{
    protected $signature = 'ams2:process
        {--dry-run : Print the enrol payload each eligible row would send, without sending it}';

    protected $description = 'Advance Enrolment v2 rows through the processing machine.';

    public function handle(Ams2Processor $processor, Ams2DryRun $dryRun): int
    {
        if ($this->option('dry-run')) {
            return $this->showDryRun($dryRun);
        }

        if (! Ams2Processor::enabled()) {
            $this->warn('Enrolment v2 is disabled (AMS2_ENABLED). Nothing was processed.');

            return self::SUCCESS;
        }

        /*
         * Named here rather than left as a generic failure: the row statuses
         * would give no hint that the cause is a missing .env value.
         */
        if ($missing = Ams2Processor::missingConfiguration()) {
            $this->error('Enrolment v2 is not configured. Nothing was processed.');

            foreach ($missing as $key) {
                $this->line('  missing: '.$key);
            }

            return self::FAILURE;
        }

        if (! $processor->run()) {
            $this->info('Another Enrolment v2 run is already in progress; nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Enrolment v2 processor run complete.');

        return self::SUCCESS;
    }

    /**
     * Deliberately available while the kill switch is off: inspecting a payload
     * before enabling the processor is the whole point.
     */
    private function showDryRun(Ams2DryRun $dryRun): int
    {
        $rows = $dryRun->eligibleRows();

        if ($rows->isEmpty()) {
            $this->info('Dry run: no rows are waiting to be enrolled.');

            return self::SUCCESS;
        }

        $this->warn('Dry run: nothing is sent and no status changes.');
        $this->newLine();

        foreach ($rows as $row) {
            $this->line($dryRun->toJson($row));
            $this->newLine();
        }

        $this->info($rows->count().' row(s) would be enrolled.');

        return self::SUCCESS;
    }
}

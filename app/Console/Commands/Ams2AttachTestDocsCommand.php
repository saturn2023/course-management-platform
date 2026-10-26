<?php

namespace App\Console\Commands;

use App\Enrolment\V2\Ams2DocumentTypes;
use App\Enrolment\V2\Ams2TestDocuments;
use App\Models\Ams2Enrolment;
use Illuminate\Console\Command;

/**
 * Attaches the two fixture documents to a row that already exists, so the
 * documents step can be retested without creating another enrolment in RTO
 * Data.
 *
 * Refuses to run in production, like ams2:make-test-row.
 */
class Ams2AttachTestDocsCommand extends Command
{
    protected $signature = 'ams2:attach-test-docs
        {id : The ams2_enrolments row id}
        {--force : Attach again even if the row already has documents}';

    protected $description = 'Attach two fixture documents to an existing Enrolment v2 row (non-production only).';

    public function handle(Ams2TestDocuments $documents): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $row = Ams2Enrolment::find((int) $this->argument('id'));

        if (! $row) {
            $this->error('No enrolment row with id '.$this->argument('id').'.');

            return self::FAILURE;
        }

        $existing = $row->documents()->count();

        if ($existing > 0 && ! $this->option('force')) {
            /*
             * Attaching a second copy would send the same file to RTO Data
             * twice, so it takes --force.
             */
            $this->error($row->reference().' already has '.$existing.' document(s). Use --force to attach anyway.');

            return self::FAILURE;
        }

        foreach ($documents->attach($row) as $document) {
            $this->line('  attached: '.$document->doc_type.' — "'.$document->original_name.'"');
        }

        /*
         * Tell the documents step what to expect and that uploading is over,
         * the way the form does when the learner finishes.
         */
        $row->update([
            'docs_expected' => implode(',', Ams2DocumentTypes::keys()),
            'docs_final' => true,
        ]);

        $this->info('Attached to '.$row->reference().' (status '.$row->status.').');

        return self::SUCCESS;
    }
}

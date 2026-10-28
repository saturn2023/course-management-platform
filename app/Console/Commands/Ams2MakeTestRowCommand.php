<?php

namespace App\Console\Commands;

use App\Enrolment\V2\Ams2DocumentTypes;
use App\Enrolment\V2\Ams2Processor;
use App\Enrolment\V2\Ams2Status;
use App\Enrolment\V2\Ams2TestDocuments;
use App\Models\Ams2Enrolment;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates one Enrolment v2 row, so the machine can be exercised against RTO
 * Data before the form is wired up.
 *
 * The name, date of birth and gender are options because matching an EXISTING
 * RTO Data learner exactly is how the verify path and the re-enrol guard get
 * tested — RTO Data matches on name, so a typo quietly creates a second learner
 * rather than finding the first.
 *
 * Refuses to run in production: this writes a row the processor will push to
 * the live RTO Data account.
 */
class Ams2MakeTestRowCommand extends Command
{
    protected $signature = 'ams2:make-test-row
        {--first=Test : Learner first name}
        {--last=Student : Learner last name}
        {--dob=1990-01-01 : Date of birth, Y-m-d}
        {--gender=M : Gender code sent to RTO Data}
        {--course=32 : RTO Data course_id}
        {--plan=38 : RTO Data plan_id}
        {--email= : Where the confirmation should eventually go}
        {--with-docs : Attach two fixture documents (one JPG, one PDF)}';

    protected $description = 'Create one Enrolment v2 test row (non-production only).';

    public function handle(Ams2TestDocuments $documents): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production: this creates a row that will be sent to the live RTO Data account.');

            return self::FAILURE;
        }

        $first = trim((string) $this->option('first'));
        $last = trim((string) $this->option('last'));
        $dob = trim((string) $this->option('dob'));

        if ($first === '' || $last === '') {
            $this->error('--first and --last cannot be empty.');

            return self::FAILURE;
        }

        /*
         * Checked here rather than left to the enrol step: a bad date is
         * treated as absent, which parks the row — a confusing way to find out
         * about a typo.
         */
        if (! $this->isValidDate($dob)) {
            $this->error('--dob must be a real date in Y-m-d format, e.g. 1990-01-01.');

            return self::FAILURE;
        }

        $withDocs = (bool) $this->option('with-docs');

        $row = Ams2Enrolment::create([
            'secret_key' => 'test-'.Str::lower((string) Str::ulid()),
            'order_id' => 0,
            'course_id' => (int) $this->option('course'),
            'plan_id' => (int) $this->option('plan'),
            'course_code' => 'TEST',
            'course_title' => '',
            'real_email' => (string) ($this->option('email') ?: 'test.student@example.test'),
            'status' => Ams2Status::RECEIVED,

            /*
             * docs_expected lists what the learner said they would upload, and
             * docs_final says the uploads have finished. With no documents,
             * WordPress sets docs_final = 1 so the step does not sit waiting.
             */
            'docs_expected' => $withDocs ? implode(',', Ams2DocumentTypes::keys()) : '',
            'docs_final' => ! $withDocs,

            'student_data' => [
                'first_name' => $first,
                'last_name' => $last,
                'date_of_birth' => $dob,
                'gender' => (string) $this->option('gender'),
                'mobile_phone' => '0400000000',
                'street_number' => '1',
                'street_name' => 'Test Street',
                'suburb' => 'Perth',
                'state' => '05',
                'postcode' => '6000',
                'country_of_birth' => '1101',
                'city_of_birth' => 'Perth',
                'main_language' => '1201',
                'atsi_status' => '4',
                'disability' => 'N',
                'labour_force' => '01',
                'school_level' => '12',
                'currently_at_school' => 'N',
                'study_reason' => '01',
                'postal_address_type' => 'same',
                'other_qualifications' => 'No',
            ],
        ]);

        $this->info('Created '.$row->displayRef().' (row id '.$row->id.')');
        $this->line('  learner     : '.$first.' '.$last.'  dob '.$dob.'  gender '.$row->student_data['gender']);
        $this->line('  course/plan : '.$row->course_id.' / '.$row->plan_id);
        $this->line('  status      : '.$row->status);
        $this->line('  placeholder : '.$row->placeholderEmail());
        $this->line('  real email  : '.$row->real_email);

        if ($withDocs) {
            foreach ($documents->attach($row) as $document) {
                $this->line('  document    : '.$document->doc_type.' — "'.$document->original_name.'"');
            }

            // docs_final flips once the uploads are finished, as the form does.
            $row->update(['docs_final' => true]);
        }

        $this->newLine();

        if (! Ams2Processor::enabled()) {
            $this->warn('AMS2_ENABLED is off, so ams2:process will not touch this row yet.');
        } else {
            $this->comment('Run: php artisan ams2:process --dry-run');
        }

        return self::SUCCESS;
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTime && $date->format('Y-m-d') === $value;
    }
}

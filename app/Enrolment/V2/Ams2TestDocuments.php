<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes two fixture documents against an enrolment row, so the documents step
 * can be exercised without going through the form.
 *
 * The filenames matter here. The stored name is randomised, exactly as a real
 * upload would be, while `original_name` carries the name a learner would have
 * chosen — because the whole point of the 19/10 fix is that RTO Data must
 * receive the ORIGINAL name, and a fixture that stored them identically would
 * hide a regression.
 *
 * Test-only. Nothing in the enrolment flow calls this.
 */
class Ams2TestDocuments
{
    public const DISK = 'local';

    public const DIRECTORY = 'ams2-documents';

    /**
     * Real files, committed to the repo rather than built inline.
     *
     * The first attempt used a hand-written 193-byte PDF with no xref table —
     * structurally invalid, so RTO Data could reasonably reject it and the
     * fixture itself would be a candidate cause of any failure. These are a
     * conforming one-page PDF and a photo-sized JPEG, so file content can be
     * ruled out when a live upload misbehaves.
     */
    public const FIXTURE_DIRECTORY = 'resources/fixtures/ams2';

    public const ID_FIXTURE = 'test-id.jpg';

    public const TRANSCRIPT_FIXTURE = 'test-vet-transcript.pdf';

    /**
     * Attach an ID document and a VET transcript to a row.
     *
     * @return array<int, Ams2EnrolmentDocument>
     */
    public function attach(Ams2Enrolment $row): array
    {
        $name = $this->learnerName($row);

        return [
            $this->write(
                $row,
                Ams2DocumentTypes::ID_DOCUMENT,
                $name.' ID.jpg',
                $this->fixture(self::ID_FIXTURE),
                'image/jpeg',
            ),
            $this->write(
                $row,
                Ams2DocumentTypes::VET_TRANSCRIPT,
                $name.' VET Transcript.pdf',
                $this->fixture(self::TRANSCRIPT_FIXTURE),
                'application/pdf',
            ),
        ];
    }

    private function write(
        Ams2Enrolment $row,
        string $type,
        string $originalName,
        string $contents,
        string $mime,
    ): Ams2EnrolmentDocument {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        // Randomised on disk, exactly as a real upload is stored.
        $path = self::DIRECTORY.'/'.$row->id.'/'.Str::lower((string) Str::ulid()).'.'.$extension;

        Storage::disk(self::DISK)->put($path, $contents);

        return Ams2EnrolmentDocument::create([
            'enrolment_row' => $row->id,
            'doc_type' => $type,
            'file_path' => $path,
            'original_name' => $originalName,
            'mime' => $mime,
            'size_bytes' => strlen($contents),
            'status' => Ams2EnrolmentDocument::STATUS_PENDING,
        ]);
    }

    private function fixture(string $file): string
    {
        $path = base_path(self::FIXTURE_DIRECTORY.'/'.$file);

        if (! is_file($path)) {
            throw new RuntimeException('Missing Enrolment v2 test fixture: '.$path);
        }

        return (string) file_get_contents($path);
    }

    private function learnerName(Ams2Enrolment $row): string
    {
        $data = (array) ($row->student_data ?? []);

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        return $name !== '' ? $name : 'Test Student';
    }
}

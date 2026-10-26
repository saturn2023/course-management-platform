<?php

namespace App\Enrolment\V2;

use App\Models\Ams2Enrolment;
use App\Models\Ams2EnrolmentDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Smallest valid files that still carry real magic bytes, so MIME
     * detection sees application/pdf and image/jpeg rather than text/plain.
     */
    private const PDF = "%PDF-1.4\n"
        ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n"
        ."%%EOF\n";

    /** A 1x1 baseline JPEG. */
    private const JPEG_BASE64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
        .'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAAB'
        .'AAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==';

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
                base64_decode(self::JPEG_BASE64),
                'image/jpeg',
            ),
            $this->write(
                $row,
                Ams2DocumentTypes::VET_TRANSCRIPT,
                $name.' VET Transcript.pdf',
                self::PDF,
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

    private function learnerName(Ams2Enrolment $row): string
    {
        $data = (array) ($row->student_data ?? []);

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        return $name !== '' ? $name : 'Test Student';
    }
}

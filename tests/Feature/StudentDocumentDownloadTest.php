<?php

namespace Tests\Feature;

use App\Models\StudentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(StudentDocument::DISK);
    }

    private function makeDocument(array $attributes = []): StudentDocument
    {
        Storage::disk(StudentDocument::DISK)->put('student-documents/AbC123random.pdf', '%PDF-1.4 handbook');

        return StudentDocument::create([
            'title' => 'Learner Handbook v3.2 26062023',
            'slug' => 'learner-handbook',
            'file_path' => 'student-documents/AbC123random.pdf',
            'original_name' => 'Learner Handbook v3.2 26062023 FINAL.PDF',
            ...$attributes,
        ]);
    }

    public function test_active_file_downloads_with_a_clean_filename(): void
    {
        $document = $this->makeDocument();

        $response = $this->get(route('student-documents.download', $document));

        $response->assertOk();
        $response->assertDownload('learner-handbook-v32-26062023.pdf');
        $this->assertSame('%PDF-1.4 handbook', $response->streamedContent());
        $this->assertStringEndsWith('/student-information/documents/learner-handbook', route('student-documents.download', $document));
    }

    public function test_inactive_document_returns_404(): void
    {
        $document = $this->makeDocument(['is_active' => false]);

        $this->get(route('student-documents.download', $document))->assertNotFound();
    }

    public function test_missing_file_returns_404(): void
    {
        $document = $this->makeDocument();
        Storage::disk(StudentDocument::DISK)->delete($document->file_path);

        $this->get(route('student-documents.download', $document))->assertNotFound();
    }

    public function test_link_only_item_returns_404(): void
    {
        $document = StudentDocument::create([
            'title' => 'Certificate Check',
            'slug' => 'certificate-check',
            'url' => 'https://example.com/check',
        ]);

        $this->get(route('student-documents.download', $document))->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/student-information/documents/does-not-exist')->assertNotFound();
    }
}

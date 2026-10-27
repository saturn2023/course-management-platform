<?php

namespace Tests\Feature;

use App\Filament\Resources\StudentDocuments\Pages\CreateStudentDocument;
use App\Filament\Resources\StudentDocuments\Pages\EditStudentDocument;
use App\Models\StudentDocument;
use App\Models\StudentInformationPageSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StudentInformationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(StudentDocument::DISK);
    }

    private function actingAsAdmin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'is_admin' => true,
            'can_use_purchase_order' => false,
        ]));
    }

    public function test_page_renders_defaults_with_no_settings_row(): void
    {
        $this->get('/student-information')
            ->assertOk()
            ->assertSee('<h1 class="ams-student-info-hero__title">Student Information</h1>', false)
            ->assertSee('STUDENT INFORMATION')
            ->assertSee('Feel free to explore our student services information.<br />', false)
            ->assertSee('rules, costs, refunds, etc.');
    }

    public function test_intro_text_is_escaped_with_line_breaks_kept(): void
    {
        StudentInformationPageSetting::create([
            'intro_text' => "First line\n<script>alert(1)</script>",
        ]);

        $this->get('/student-information')
            ->assertSee('First line<br />', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_documents_list_in_order_with_correct_link_types(): void
    {
        StudentDocument::create(['title' => 'Certificate Check', 'slug' => 'certificate-check', 'url' => 'https://example.com/check', 'sort_order' => 2]);
        StudentDocument::create(['title' => 'Learner Handbook', 'slug' => 'learner-handbook', 'file_path' => 'student-documents/a.pdf', 'sort_order' => 1]);
        StudentDocument::create(['title' => 'Hidden Form', 'slug' => 'hidden-form', 'file_path' => 'student-documents/b.pdf', 'is_active' => false]);

        $this->get('/student-information')
            ->assertSeeInOrder(['Learner Handbook', 'Certificate Check'])
            ->assertSee('href="'.route('student-documents.download', 'learner-handbook').'"', false)
            ->assertSee('href="https://example.com/check" target="_blank" rel="noopener"', false)
            ->assertDontSee('Hidden Form');
    }

    public function test_old_url_redirects_temporarily(): void
    {
        $this->get('/student-info')->assertRedirect('/student-information')->assertStatus(302);
    }

    public function test_replacing_a_file_deletes_the_old_one(): void
    {
        $disk = Storage::disk(StudentDocument::DISK);
        $disk->put('student-documents/old.pdf', 'old');
        $disk->put('student-documents/new.pdf', 'new');

        $document = StudentDocument::create(['title' => 'Refund Request Form', 'slug' => 'refund-request-form', 'file_path' => 'student-documents/old.pdf']);
        $document->update(['file_path' => 'student-documents/new.pdf']);

        $disk->assertMissing('student-documents/old.pdf');
        $disk->assertExists('student-documents/new.pdf');
        $this->assertSame('refund-request-form', $document->fresh()->slug);
    }

    public function test_deleting_a_record_deletes_its_file(): void
    {
        $disk = Storage::disk(StudentDocument::DISK);
        $disk->put('student-documents/form.pdf', 'pdf');

        StudentDocument::create(['title' => 'Complaints and Appeals', 'slug' => 'complaints-and-appeals', 'file_path' => 'student-documents/form.pdf'])->delete();

        $disk->assertMissing('student-documents/form.pdf');
    }

    public function test_admin_must_give_a_file_or_a_link(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateStudentDocument::class)
            ->fillForm(['title' => 'Refund Request Form', 'slug' => 'refund-request-form'])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }

    public function test_admin_cannot_give_both_a_file_and_a_link(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateStudentDocument::class)
            ->fillForm([
                'title' => 'Refund Request Form',
                'slug' => 'refund-request-form',
                'file_path' => UploadedFile::fake()->create('form.pdf', 100, 'application/pdf'),
                'url' => 'https://example.com/form',
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }

    public function test_admin_upload_stores_file_privately_with_original_name(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateStudentDocument::class)
            ->fillForm([
                'title' => 'Learner Handbook v3.2 26062023',
                'file_path' => UploadedFile::fake()->create('Handbook.pdf', 100, 'application/pdf'),
            ])
            ->assertSchemaStateSet(['slug' => 'learner-handbook-v32-26062023'])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = StudentDocument::sole();
        $this->assertSame('Handbook.pdf', $document->original_name);
        $this->assertStringStartsWith('student-documents/', $document->file_path);
        Storage::disk(StudentDocument::DISK)->assertExists($document->file_path);
    }

    public function test_link_only_item_saves(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateStudentDocument::class)
            ->fillForm(['title' => 'Certificate Check', 'slug' => 'certificate-check', 'url' => 'https://example.com/check'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(StudentDocument::sole()->file_path);
    }

    public function test_editing_the_title_does_not_change_the_slug(): void
    {
        $this->actingAsAdmin();
        $document = StudentDocument::create(['title' => 'Certificate Check', 'slug' => 'certificate-check', 'url' => 'https://example.com/check']);

        Livewire::test(EditStudentDocument::class, ['record' => $document->getRouteKey()])
            ->fillForm(['title' => 'Check a Certificate'])
            ->assertSchemaStateSet(['slug' => 'certificate-check'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('certificate-check', $document->fresh()->slug);
    }
}

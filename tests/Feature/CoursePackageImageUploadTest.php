<?php

namespace Tests\Feature;

use App\Filament\Resources\CoursePackages\Pages\EditCoursePackage;
use App\Models\CoursePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drives the real Filament EditCoursePackage page so the FileUpload ->
 * database path is covered without a browser.
 *
 * The second test is the one worth having: a FileUpload field left alone can
 * silently null its column when an unrelated field is saved, which looks like
 * the image was never uploaded.
 */
class CoursePackageImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_1_upload_persists(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create());

        $package = CoursePackage::create([
            'title' => "Confined Space \n& Gas Test Online Refresher",
            'codes' => 'RIIWHS202E & MSMWHS217',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->image('probe.png', 800, 600);

        Livewire::test(EditCoursePackage::class, ['record' => $package->getKey()])
            ->fillForm(['image_1' => [$file]])
            ->call('save')
            ->assertHasNoFormErrors();

        $package->refresh();

        $this->assertNotNull($package->image_1, 'image_1 was not written to the database');
        $this->assertStringStartsWith('package-images/', $package->image_1);
        Storage::disk('public')->assertExists($package->image_1);
    }

    public function test_existing_image_is_preserved_when_editing_other_fields(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create());

        Storage::disk('public')->put('package-images/existing.webp', 'x');

        $package = CoursePackage::create([
            'title' => 'Keep my image',
            'status' => 'active',
            'image_1' => 'package-images/existing.webp',
        ]);

        Livewire::test(EditCoursePackage::class, ['record' => $package->getKey()])
            ->fillForm(['codes' => 'CHANGED'])
            ->call('save')
            ->assertHasNoFormErrors();

        $package->refresh();

        $this->assertSame('package-images/existing.webp', $package->image_1);
    }
}

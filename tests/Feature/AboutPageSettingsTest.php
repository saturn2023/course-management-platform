<?php

namespace Tests\Feature;

use App\Filament\Pages\AboutPageSettings;
use App\Models\AboutPageSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AboutPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'is_admin' => true,
            'can_use_purchase_order' => false,
        ]));
    }

    public function test_uploaded_hero_image_requires_alt_text(): void
    {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([
                'hero_image' => UploadedFile::fake()->image('hero.jpg', 1920, 700),
                'hero_image_alt' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['hero_image_alt' => 'required_with']);
    }

    public function test_uploaded_hero_image_saves_with_alt_text(): void
    {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([
                'hero_image' => UploadedFile::fake()->image('hero.jpg', 1920, 700),
                'hero_image_alt' => 'Trainer on a forklift',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = AboutPageSetting::current();
        $this->assertSame('Trainer on a forklift', $settings->heroImageAlt());
        Storage::disk('public')->assertExists($settings->hero_image);
    }

    public function test_alt_text_is_optional_while_the_default_photo_is_used(): void
    {
        Livewire::test(AboutPageSettings::class)
            ->fillForm([
                'hero_image' => null,
                'hero_image_alt' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(AboutPageSetting::DEFAULT_HERO_IMAGE_ALT, AboutPageSetting::current()->heroImageAlt());
    }
}

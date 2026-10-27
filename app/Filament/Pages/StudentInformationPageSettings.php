<?php

namespace App\Filament\Pages;

use App\Models\StudentInformationPageSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Single settings page for /student-information (Admin → Student Information
 * Page). Backed by the one-row student_information_page_settings table — edit
 * only, no create/delete. Same structure as AboutPageSettings.
 *
 * The documents list is managed separately at Admin → Student Documents.
 *
 * @property-read Schema $form
 */
class StudentInformationPageSettings extends Page
{
    use InteractsWithFormActions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Student Information Page';

    protected static ?string $title = 'Student Information Page';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.student-information-page-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(StudentInformationPageSetting::instance()->attributesToArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model(StudentInformationPageSetting::instance())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero banner')
                    ->description('The full-width photo at the top of the page. A dark overlay is always applied so the title stays readable.')
                    ->schema([
                        FileUpload::make('hero_image')
                            ->label('Background image')
                            ->image()
                            ->disk('public')
                            ->directory('student-information-page')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Wide/landscape photos work best (the current one is 1920×700). Leave empty to use the default mine-site photo.')
                            ->columnSpanFull(),

                        TextInput::make('hero_image_alt')
                            ->label('Image alt text')
                            ->maxLength(255)
                            ->requiredWith('hero_image')
                            ->placeholder(StudentInformationPageSetting::DEFAULT_HERO_IMAGE_ALT)
                            ->helperText('Describe the photo for screen readers. Required once you upload a photo. Leave empty while the default photo is shown to use the default description.'),

                        TextInput::make('hero_title')
                            ->label('Title')
                            ->maxLength(100)
                            ->placeholder(StudentInformationPageSetting::DEFAULT_HERO_TITLE)
                            ->helperText('Leave empty for "'.StudentInformationPageSetting::DEFAULT_HERO_TITLE.'".'),
                    ])
                    ->columns(2),

                Section::make('Introduction')
                    ->description('The heading and text above the documents list. The documents themselves are managed at Admin → Student Documents.')
                    ->schema([
                        TextInput::make('intro_heading')
                            ->label('Heading')
                            ->maxLength(255)
                            ->placeholder(StudentInformationPageSetting::DEFAULT_INTRO_HEADING)
                            ->helperText('Shown exactly as typed — type it in capitals if you want capitals. Leave empty for "'.StudentInformationPageSetting::DEFAULT_INTRO_HEADING.'".'),

                        /*
                         * Textarea, not a rich editor: the page escapes this
                         * value and converts only newlines to <br>, same as
                         * the review text. No admin-entered markup is rendered.
                         */
                        Textarea::make('intro_text')
                            ->label('Text')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder(StudentInformationPageSetting::DEFAULT_INTRO_TEXT)
                            ->helperText('Plain text only — HTML is not rendered. Each new line starts a new line on the page. Leave empty for the default text.')
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(70)
                            ->placeholder(StudentInformationPageSetting::DEFAULT_META_TITLE)
                            ->helperText('The browser tab / Google result title. Around 60 characters. Leave empty for the default.'),

                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(320)
                            ->placeholder(StudentInformationPageSetting::DEFAULT_META_DESCRIPTION)
                            ->helperText('The snippet under the title in Google. Around 155 characters. Leave empty for the default.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        StudentInformationPageSetting::instance()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Student Information page saved')
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->key('form-actions'),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}

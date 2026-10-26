<?php

namespace App\Filament\Pages;

use App\Models\AboutPageSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * Single settings page for the /about page (Admin → About Page). Backed by the
 * one-row about_page_settings table — edit only, no create/delete. Same
 * structure as HomepageSettings.
 *
 * @property-read Schema $form
 */
class AboutPageSettings extends Page
{
    use InteractsWithFormActions;

    /** Same toolbar as the course content editors (CourseForm::RICH_TOOLBAR). */
    private const RICH_TOOLBAR = [
        'bold',
        'italic',
        'link',
        'h2',
        'h3',
        'bulletList',
        'orderedList',
        'undo',
        'redo',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?string $navigationLabel = 'About Page';

    protected static ?string $title = 'About Page';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.about-page-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(AboutPageSetting::instance()->attributesToArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model(AboutPageSetting::instance())
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
                            ->directory('about-page')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Wide/landscape photos work best (the current one is 1920×700). Leave empty to use the default mine-site photo.')
                            ->columnSpanFull(),

                        TextInput::make('hero_image_alt')
                            ->label('Image alt text')
                            ->maxLength(255)
                            ->placeholder(AboutPageSetting::DEFAULT_HERO_IMAGE_ALT)
                            ->helperText('Describe the photo for screen readers. Leave empty to use the default description while the default photo is shown; an uploaded photo with no alt text is treated as decorative.'),

                        TextInput::make('hero_title')
                            ->label('Title')
                            ->maxLength(100)
                            ->placeholder(AboutPageSetting::DEFAULT_HERO_TITLE)
                            ->helperText('Leave empty for "'.AboutPageSetting::DEFAULT_HERO_TITLE.'".'),
                    ])
                    ->columns(2),

                Section::make('About content')
                    ->schema([
                        TextInput::make('content_heading')
                            ->label('Heading')
                            ->maxLength(255)
                            ->placeholder(AboutPageSetting::DEFAULT_CONTENT_HEADING)
                            ->helperText('Leave empty for "'.AboutPageSetting::DEFAULT_CONTENT_HEADING.'".'),

                        RichEditor::make('content_body')
                            ->label('Body')
                            ->toolbarButtons(self::RICH_TOOLBAR)
                            ->columnSpanFull(),
                    ]),

                Section::make('Trainers')
                    ->schema([
                        TextInput::make('trainers_heading')
                            ->label('Section heading')
                            ->maxLength(255)
                            ->placeholder(AboutPageSetting::DEFAULT_TRAINERS_HEADING)
                            ->helperText('Leave empty for "'.AboutPageSetting::DEFAULT_TRAINERS_HEADING.'".'),

                        Repeater::make('trainers')
                            ->label('Trainers')
                            ->schema([
                                FileUpload::make('photo')
                                    ->label('Photo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('about-page/trainers')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->helperText('Square or portrait works best. Leave empty to show the AMS logo.'),

                                TextInput::make('photo_alt')
                                    ->label('Photo alt text')
                                    ->maxLength(255)
                                    ->helperText('Describe the photo for screen readers. Leave empty to use the trainer\'s name.'),

                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),

                                RichEditor::make('bio')
                                    ->label('Bio')
                                    ->toolbarButtons(self::RICH_TOOLBAR)
                                    ->columnSpanFull(),
                            ])
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable(false)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addActionLabel('Add trainer')
                            ->helperText('Drag (or use the arrows) to set the order they appear on the page.')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),

                Section::make('Mission statement')
                    ->schema([
                        TextInput::make('mission_heading')
                            ->label('Heading')
                            ->maxLength(255)
                            ->placeholder(AboutPageSetting::DEFAULT_MISSION_HEADING)
                            ->helperText('Leave empty for "'.AboutPageSetting::DEFAULT_MISSION_HEADING.'".'),

                        Repeater::make('mission_points')
                            ->label('Bullet points')
                            ->simple(
                                Textarea::make('point')
                                    ->rows(2)
                                    ->required()
                                    ->maxLength(1000),
                            )
                            ->reorderableWithButtons()
                            ->addActionLabel('Add bullet point')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),

                Section::make('Google reviews')
                    ->description('The same reviews carousel used on the homepage and course pages. The reviews themselves are managed at Admin → Reviews.')
                    ->schema([
                        Toggle::make('show_reviews')
                            ->label('Show the reviews section on this page')
                            ->default(false),
                    ]),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(70)
                            ->placeholder(AboutPageSetting::DEFAULT_META_TITLE)
                            ->helperText('The browser tab / Google result title. Around 60 characters. Leave empty for the default.'),

                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(320)
                            ->placeholder(AboutPageSetting::DEFAULT_META_DESCRIPTION)
                            ->helperText('The snippet under the title in Google. Around 155 characters. Leave empty for the default.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        AboutPageSetting::instance()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('About page saved')
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

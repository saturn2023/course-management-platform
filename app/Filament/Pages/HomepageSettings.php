<?php

namespace App\Filament\Pages;

use App\Models\HomepageSetting;
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
 * Single settings page for editable homepage content (Admin → Homepage
 * Settings). Backed by the one-row homepage_settings table.
 *
 * Add future homepage sections as another Section() below rather than as a new
 * resource, so homepage content stays in one place.
 *
 * @property-read Schema $form
 */
class HomepageSettings extends Page
{
    use InteractsWithFormActions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Homepage Settings';

    protected static ?string $title = 'Homepage Settings';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.homepage-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(HomepageSetting::instance()->attributesToArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model(HomepageSetting::instance())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('About banner')
                    ->description('The banner below the Package Online Refresher courses on the homepage.')
                    ->schema([
                        FileUpload::make('about_background_image')
                            ->label('Background image')
                            ->image()
                            ->disk('public')
                            ->directory('homepage-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Fills the whole banner. Wide/landscape photos work best — the subject should sit on the right, as the left is darkened for the heading. Leave empty to use the default image.'),

                        FileUpload::make('about_logo')
                            ->label('Logo (inside the banner)')
                            ->image()
                            ->disk('public')
                            ->directory('homepage-images')
                            ->visibility('public')
                            ->helperText('Shown above the heading. This is separate from the site header logo. Leave empty to use the default AMS logo.'),

                        Textarea::make('about_heading')
                            ->label('Heading')
                            ->rows(2)
                            ->maxLength(255)
                            ->placeholder(HomepageSetting::DEFAULT_ABOUT_HEADING)
                            ->helperText('Press Enter to force a line break, e.g. "Over two decades of" then "experience, dedication and service." Leave empty for the default.')
                            ->columnSpanFull(),

                        TextInput::make('about_button_text')
                            ->label('Button text')
                            ->maxLength(100)
                            ->placeholder(HomepageSetting::DEFAULT_ABOUT_BUTTON_TEXT)
                            ->helperText('Leave empty for "'.HomepageSetting::DEFAULT_ABOUT_BUTTON_TEXT.'".'),

                        TextInput::make('about_button_url')
                            ->label('Button URL')
                            ->maxLength(255)
                            ->placeholder(HomepageSetting::DEFAULT_ABOUT_BUTTON_URL)
                            ->helperText('A path on this site such as /about, or a full https:// address. Leave empty for '.HomepageSetting::DEFAULT_ABOUT_BUTTON_URL.'.'),
                    ]),

                Section::make('Experience Section')
                    ->description('The white card over the large photo, directly below the About banner.')
                    ->schema([
                        FileUpload::make('experience_background_image')
                            ->label('Background image')
                            ->image()
                            ->disk('public')
                            ->directory('homepage-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('The large photograph behind the white card. Wide/landscape photos work best — the subject should sit on the right, as the white card covers the left of the section. Until one is uploaded the section shows a plain grey placeholder.'),

                        FileUpload::make('experience_background_image_mobile')
                            ->label('Background image (phone)')
                            ->image()
                            ->disk('public')
                            ->directory('homepage-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Optional. On phones the photo sits above the card in its own row, so the wide desktop crop can leave a lot of empty space on one side. Upload a tighter crop focused on the subject here. Leave empty to use the main background image on phones too.'),

                        Textarea::make('experience_heading')
                            ->label('Heading')
                            ->rows(2)
                            ->maxLength(255)
                            ->placeholder(HomepageSetting::DEFAULT_EXPERIENCE_HEADING)
                            ->helperText('Wraps to two lines on its own inside the card. Press Enter only if you want to force the break somewhere else. Leave empty for the default.')
                            ->columnSpanFull(),

                        Textarea::make('experience_description')
                            ->label('Description')
                            ->rows(5)
                            ->maxLength(1000)
                            ->placeholder(HomepageSetting::DEFAULT_EXPERIENCE_DESCRIPTION)
                            ->helperText('The paragraph below the heading. Leave empty for the default.')
                            ->columnSpanFull(),

                        TextInput::make('experience_button_text')
                            ->label('Button text')
                            ->maxLength(100)
                            ->placeholder(HomepageSetting::DEFAULT_EXPERIENCE_BUTTON_TEXT)
                            ->helperText('Leave empty for "'.HomepageSetting::DEFAULT_EXPERIENCE_BUTTON_TEXT.'".'),

                        TextInput::make('experience_button_url')
                            ->label('Button URL')
                            ->maxLength(255)
                            ->placeholder(HomepageSetting::DEFAULT_EXPERIENCE_BUTTON_URL)
                            ->helperText('A path on this site such as /courses, an anchor such as #courses, or a full https:// address. Leave empty for '.HomepageSetting::DEFAULT_EXPERIENCE_BUTTON_URL.'.'),
                    ]),

                Section::make('First In Our Field Section')
                    ->description('The section below Experience. Same design mirrored — photograph on the left, white card on the right.')
                    ->schema([
                        FileUpload::make('first_field_background_image')
                            ->label('Background image')
                            ->image()
                            ->disk('public')
                            ->directory('homepage-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('The large photograph behind the white card. Wide/landscape photos work best — the subject should sit on the LEFT here, as the white card covers the right of the section. Until one is uploaded the section shows a plain grey placeholder.'),

                        FileUpload::make('first_field_background_image_mobile')
                            ->label('Background image (tablet & phone)')
                            ->image()
                            ->disk('public')
                            ->directory('homepage-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Optional. At 900px and below the photo sits above the card in its own row, so the wide desktop crop can leave a lot of empty space on one side. Upload a tighter crop focused on the subject here. Leave empty to use the main background image at every size.'),

                        Textarea::make('first_field_heading')
                            ->label('Heading')
                            ->rows(2)
                            ->maxLength(255)
                            ->placeholder(HomepageSetting::DEFAULT_FIRST_FIELD_HEADING)
                            ->helperText('Wraps on its own inside the card. Press Enter only if you want to force the break somewhere else. Leave empty for the default.')
                            ->columnSpanFull(),

                        Textarea::make('first_field_description')
                            ->label('Description')
                            ->rows(5)
                            ->maxLength(1000)
                            ->placeholder(HomepageSetting::DEFAULT_FIRST_FIELD_DESCRIPTION)
                            ->helperText('The paragraph below the heading. Leave empty for the default.')
                            ->columnSpanFull(),

                        TextInput::make('first_field_button_text')
                            ->label('Button text')
                            ->maxLength(100)
                            ->placeholder(HomepageSetting::DEFAULT_FIRST_FIELD_BUTTON_TEXT)
                            ->helperText('Leave empty for "'.HomepageSetting::DEFAULT_FIRST_FIELD_BUTTON_TEXT.'".'),

                        TextInput::make('first_field_button_url')
                            ->label('Button URL')
                            ->maxLength(255)
                            ->placeholder(HomepageSetting::DEFAULT_FIRST_FIELD_BUTTON_URL)
                            ->helperText('A path on this site such as /courses, an anchor such as #courses, or a full https:// address. Leave empty for '.HomepageSetting::DEFAULT_FIRST_FIELD_BUTTON_URL.'.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        HomepageSetting::instance()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Homepage settings saved')
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

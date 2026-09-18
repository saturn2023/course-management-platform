<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Models\Course;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseForm
{
    /**
     * Toolbar shared by every rich-text field: paragraphs, bold/italic,
     * headings, both list types and links — what the course copy actually
     * needs. Media and raw-HTML buttons are deliberately left out so the
     * markup that reaches the frontend stays predictable.
     *
     * @var array<int, string>
     */
    private const RICH_TOOLBAR = [
        'bold',
        'italic',
        'link',
        'h2',
        'h3',
        'bulletList',
        'orderedList',
        'redo',
        'undo',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                 * All pre-existing fields, unchanged — only grouped into
                 * sections so the form does not become one enormous page now
                 * that the detail-page content lives here too.
                 */
                Section::make('Basic information')
                    ->schema([
                        TextInput::make('title')
                            ->required(),

                        TextInput::make('code')
                            ->label('Course code')
                            ->default(null),

                        TextInput::make('slug')
                            ->helperText('Public URL: /courses/{slug} — this is where the homepage "View course" button links to. Leave blank and it is generated from the title and code on save. Pasting a full course URL works too; only the last part is kept.')
                            ->default(null),

                        TextInput::make('status')
                            ->required()
                            ->default('active')
                            ->helperText('Only "active" courses are reachable at /courses/{slug}.'),

                        Textarea::make('description')
                            ->label('Short description (admin/card)')
                            ->default(null)
                            ->columnSpanFull(),

                        TextInput::make('course_type')
                            ->label('Course type')
                            ->maxLength(100)
                            ->placeholder('Online Refresher Course')
                            ->helperText('Shown in the detail page meta row. Leave empty to hide that item.')
                            ->default(null),

                        TextInput::make('duration')
                            ->label('Duration')
                            ->maxLength(100)
                            ->placeholder('2 - 4 hours')
                            ->helperText('Shown in the detail page meta row. Leave empty to hide that item.')
                            ->default(null),

                        Toggle::make('nationally_accredited')
                            ->label('Nationally accredited')
                            ->default(true)
                            ->helperText('Shows the Nationally Recognised Training logo in the meta row and under the price card.'),
                    ]),

                Section::make('Pricing')
                    ->description('"Price" is the amount actually charged — orders and the checkout read it. "Regular price" is only the struck-through figure beside it.')
                    ->schema([
                        TextInput::make('price')
                            ->label('Price (charged)')
                            ->required()
                            ->numeric()
                            ->default(0.0)
                            ->prefix('$'),

                        TextInput::make('regular_price')
                            ->label('Regular price (was)')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Optional. When higher than the price, the detail page shows it struck through and calculates the "Save X%" badge automatically. Leave empty for no discount styling.')
                            ->default(null),
                    ]),

                Section::make('Enrolment')
                    ->schema([
                        TextInput::make('ams_enrolment_code')
                            ->label('AMS enrolment code')
                            ->helperText('Example: 32 from the current AMS enrol URL.')
                            ->maxLength(50)
                            ->default(null),

                        TextInput::make('ams_plan_id')
                            ->label('AMS plan ID')
                            ->helperText('Example: 38 from the current AMS enrol URL.')
                            ->maxLength(50)
                            ->default(null),

                        TextInput::make('enrolment_url')
                            ->label('Enrolment URL override')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Optional. Leave empty and ENROL NOW uses the normal checkout (/checkout/load) built from the two fields above. With neither available the button renders disabled.')
                            ->default(null)
                            ->columnSpanFull(),
                    ]),

                Section::make('Course overview')
                    ->schema([
                        RichEditor::make('overview')
                            ->label('Overview')
                            ->toolbarButtons(self::RICH_TOOLBAR)
                            ->helperText('The "Course Overview" copy under the meta row. Leave empty to hide that heading entirely.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Course requirements')
                    ->schema([
                        RichEditor::make('entry_requirements_warning')
                            ->label('Entry requirements warning')
                            ->toolbarButtons(self::RICH_TOOLBAR)
                            ->helperText('Shown as the red outlined notice at the top of the Entry requirements section. Leave empty and no box is rendered.')
                            ->columnSpanFull(),

                        RichEditor::make('entry_requirements')
                            ->label('Entry requirements')
                            ->toolbarButtons(self::RICH_TOOLBAR)
                            ->columnSpanFull(),
                    ]),

                /*
                 * One accordion card per populated field. An empty field means
                 * no card at all — see Course::detailSections().
                 */
                Section::make('Course details')
                    ->description('Each field below becomes one accordion card on the course page. Leave a field empty and its card is not shown.')
                    ->collapsed()
                    ->schema([
                        RichEditor::make('course_information')->label('Course information')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('assessment')->label('Assessment')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('qualification')->label('Qualification')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('equipment_materials')->label('Equipment / Materials')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('delivery_mode')->label('Delivery mode')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                    ]),

                Section::make('Student information')
                    ->description('Also rendered as accordion cards, after the course details above.')
                    ->collapsed()
                    ->schema([
                        RichEditor::make('support_information')->label('Support information')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('student_handbook')->label('Student handbook')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('vet_transcript')->label('VET transcript')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('usi_information')->label('Unique student identifier (USI)')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('complaints_appeals')->label('Complaints & appeals')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                        RichEditor::make('cancellations_refunds')->label('Cancellations & refunds')->toolbarButtons(self::RICH_TOOLBAR)->columnSpanFull(),
                    ]),

                /*
                 * Pre-existing homepage card fields, unchanged.
                 */
                Section::make('Homepage card')
                    ->collapsed()
                    ->schema([
                        Textarea::make('card_title')
                            ->label('Homepage card title (two lines)')
                            ->rows(2)
                            ->maxLength(120)
                            ->helperText('Optional. Type the title across two lines (press Enter for the second line), e.g. "Work Safely at" then "Heights". Leave blank to use the course title.')
                            ->columnSpanFull(),

                        FileUpload::make('image_path')
                            ->label('Course image')
                            ->image()
                            ->disk('public')
                            ->directory('course-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Shown as the card image on the homepage.')
                            ->columnSpanFull(),

                        FileUpload::make('icon_path')
                            ->label('Category icon')
                            ->image()
                            ->disk('public')
                            ->directory('course-icons')
                            ->visibility('public')
                            ->helperText('Small icon overlaid on the top-right of the card image. Optional.'),

                        TextInput::make('banner_text')
                            ->label('Banner text')
                            ->default('100% ONLINE REFRESHER')
                            ->maxLength(100),

                        TextInput::make('display_order')
                            ->label('Display order')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),

                        Toggle::make('show_on_homepage')
                            ->label('Show on homepage')
                            ->default(true),
                    ]),
            ]);
    }
}

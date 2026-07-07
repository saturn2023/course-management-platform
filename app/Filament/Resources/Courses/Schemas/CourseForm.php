<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('code')
    ->label('Course code')
    ->default(null),

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

TextInput::make('slug')
    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),

                /*
                 * Frontend homepage card fields.
                 */
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

                TextInput::make('course_url')
                    ->label('View Course URL')
                    ->url()
                    ->maxLength(255)
                    ->helperText('Where the "View course" button links to. If empty, the button is disabled.')
                    ->default(null),

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
            ]);
    }
}

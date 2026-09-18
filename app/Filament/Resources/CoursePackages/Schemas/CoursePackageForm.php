<?php

namespace App\Filament\Resources\CoursePackages\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CoursePackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('title')
                    ->label('Card title (two lines)')
                    ->required()
                    ->rows(2)
                    ->maxLength(150)
                    ->helperText('Type the title across two lines (press Enter for the second line), e.g. "Confined Space &" then "Working at Heights Online Refresher".')
                    ->columnSpanFull(),

                TextInput::make('codes')
                    ->label('Course codes')
                    ->maxLength(150)
                    ->helperText('Combined codes shown above the title, e.g. "RIIWHS202E & RIIWHS204E".')
                    ->default(null),

                TextInput::make('banner_text')
                    ->label('Banner text')
                    ->default('PACKAGE ONLINE REFRESHER')
                    ->maxLength(100),

                TextInput::make('course_url')
                    ->label('View Course URL')
                    ->url()
                    ->maxLength(255)
                    ->helperText('Where the whole card links to. If empty, the card is not clickable and the button is disabled.')
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

                TextInput::make('status')
                    ->required()
                    ->default('active'),

                /*
                 * Up to three stacked images, each with an optional overlay icon.
                 * Images are shown top-to-bottom, split to equal heights.
                 */
                FileUpload::make('image_1')
                    ->label('Image 1')
                    ->image()
                    ->disk('public')
                    ->directory('package-images')
                    ->visibility('public')
                    ->imageEditor(),

                FileUpload::make('icon_1')
                    ->label('Icon 1')
                    ->image()
                    ->disk('public')
                    ->directory('package-icons')
                    ->visibility('public')
                    ->helperText('Overlaid top-right of image 1. Optional.'),

                FileUpload::make('image_2')
                    ->label('Image 2')
                    ->image()
                    ->disk('public')
                    ->directory('package-images')
                    ->visibility('public')
                    ->imageEditor(),

                FileUpload::make('icon_2')
                    ->label('Icon 2')
                    ->image()
                    ->disk('public')
                    ->directory('package-icons')
                    ->visibility('public')
                    ->helperText('Overlaid top-right of image 2. Optional.'),

                FileUpload::make('image_3')
                    ->label('Image 3')
                    ->image()
                    ->disk('public')
                    ->directory('package-images')
                    ->visibility('public')
                    ->imageEditor()
                    ->helperText('Optional third image.'),

                FileUpload::make('icon_3')
                    ->label('Icon 3')
                    ->image()
                    ->disk('public')
                    ->directory('package-icons')
                    ->visibility('public')
                    ->helperText('Overlaid top-right of image 3. Optional.'),
            ]);
    }
}

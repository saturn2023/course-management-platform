<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reviewer_name')
                    ->label('Reviewer name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown on the card. The first letter also becomes the fallback avatar when no photo is uploaded.'),

                DatePicker::make('review_date')
                    ->label('Review date')
                    ->native(false)
                    ->helperText('Optional. Hidden on the card when left empty.'),

                Select::make('rating')
                    ->label('Rating')
                    ->options([
                        5 => '5 stars',
                        4 => '4 stars',
                        3 => '3 stars',
                        2 => '2 stars',
                        1 => '1 star',
                    ])
                    ->default(5)
                    ->required()
                    ->helperText('Drawn as filled/empty stars on the card — exactly the value stored here.'),

                /*
                 * Textarea, not a rich editor: nothing else in this panel uses
                 * one. The frontend escapes this value and converts only
                 * newlines to <br>, so no admin-entered markup is ever rendered.
                 */
                Textarea::make('review_text')
                    ->label('Review text')
                    ->required()
                    ->rows(6)
                    ->maxLength(5000)
                    ->helperText('Plain text only — HTML is not rendered. Long reviews are clamped on the card with a "Read more" control.')
                    ->columnSpanFull(),

                FileUpload::make('reviewer_avatar')
                    ->label('Reviewer photo')
                    ->image()
                    ->disk('public')
                    ->directory('review-avatars')
                    ->visibility('public')
                    ->imageEditor()
                    ->avatar()
                    ->helperText('Optional. Leave empty and the card shows a coloured circle with the reviewer\'s first initial instead.'),

                TextInput::make('source')
                    ->label('Source')
                    ->maxLength(100)
                    ->default('Google')
                    ->helperText('Where the review came from, shown small in the card corner. Clear it to show nothing.'),

                TextInput::make('source_url')
                    ->label('Original review URL')
                    ->url()
                    ->maxLength(255)
                    ->helperText('Optional. When set, "Read more" opens the original review in a new tab instead of expanding the text in place.'),

                TextInput::make('display_order')
                    ->label('Display order')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Lower numbers appear first. You can also drag rows on the list screen to reorder.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Off hides the review from the homepage without deleting it.'),
            ]);
    }
}

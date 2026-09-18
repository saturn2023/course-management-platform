<?php

namespace App\Filament\Resources\EnrolmentSteps\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EnrolmentStepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown on the closed accordion row, e.g. "Step 1. Select required course."')
                    ->columnSpanFull(),

                /*
                 * Textarea, not a rich editor: nothing else in this panel uses
                 * one, and the frontend escapes this value and converts only
                 * newlines to <br>. Plain text in, plain text out — no markup is
                 * rendered, so there is nothing here to exploit.
                 */
                Textarea::make('content')
                    ->label('Content')
                    ->rows(6)
                    ->maxLength(5000)
                    ->helperText('Shown when the step is expanded. Press Enter for a new paragraph line. Plain text only — HTML is not rendered.')
                    ->columnSpanFull(),

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
                    ->helperText('Off hides the step from the homepage without deleting it.'),
            ]);
    }
}

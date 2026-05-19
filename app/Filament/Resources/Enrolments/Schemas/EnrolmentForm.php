<?php

namespace App\Filament\Resources\Enrolments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EnrolmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_id')
                    ->required()
                    ->numeric(),
                TextInput::make('student_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('course_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('external_enrolment_id')
                    ->default(null),
                TextInput::make('enrolment_link')
                    ->default(null),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('error_message')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('request_payload')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('response_payload')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}

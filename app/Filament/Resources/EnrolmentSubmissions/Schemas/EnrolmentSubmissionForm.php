<?php

namespace App\Filament\Resources\EnrolmentSubmissions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EnrolmentSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('enrolment_id')
                    ->relationship('enrolment', 'id')
                    ->required(),
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->default(null),
                Select::make('student_id')
                    ->relationship('student', 'id')
                    ->default(null),
                Select::make('course_id')
                    ->relationship('course', 'title')
                    ->default(null),
                TextInput::make('code')
                    ->default(null),
                TextInput::make('plan')
                    ->default(null),
                Textarea::make('form_data')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('id_document_path')
                    ->default(null),
                TextInput::make('vet_transcript_path')
                    ->default(null),
                DateTimePicker::make('submitted_at'),
            ]);
    }
}

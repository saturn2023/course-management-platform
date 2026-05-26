<?php

namespace App\Filament\Resources\EnrolmentSubmissions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnrolmentSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Submission Details')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Submission ID'),

                        TextEntry::make('enrolment_id')
                            ->label('Enrolment ID'),

                        TextEntry::make('order_id')
                            ->label('Order ID'),

                        TextEntry::make('student_id')
                            ->label('Student ID'),

                        TextEntry::make('course.title')
                            ->label('Course'),

                        TextEntry::make('code')
                            ->label('Code')
                            ->badge(),

                        TextEntry::make('plan')
                            ->label('Plan')
                            ->badge(),

                        TextEntry::make('submitted_at')
                            ->label('Submitted at')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Uploaded Documents')
                    ->schema([
                        TextEntry::make('id_document_path')
                            ->label('ID document')
                            ->placeholder('Not uploaded')
                            ->copyable(),

                        TextEntry::make('vet_transcript_path')
                            ->label('VET transcript')
                            ->placeholder('Not uploaded')
                            ->copyable(),
                    ])
                    ->columns(1),
                 Section::make('API Submission Status')
    ->schema([
        TextEntry::make('api_status')
            ->label('API Status')
            ->badge(),

        TextEntry::make('api_attempts')
            ->label('API Attempts'),

        TextEntry::make('api_last_attempted_at')
            ->label('Last attempted at')
            ->dateTime()
            ->placeholder('Not attempted'),

        TextEntry::make('api_submitted_at')
            ->label('Submitted to API at')
            ->dateTime()
            ->placeholder('Not submitted'),

        TextEntry::make('api_error_message')
            ->label('API Error')
            ->placeholder('No error')
            ->copyable(),

        TextEntry::make('external_reference')
            ->label('External Reference')
            ->placeholder('Not available')
            ->copyable(),
    ])
    ->columns(2),
                Section::make('Submitted Form Data')
                    ->schema([
                        TextEntry::make('form_data')
                            ->label('Form data')
                            ->state(function ($record): string {
                                if (blank($record->form_data)) {
                                    return 'No form data saved.';
                                }

                                return json_encode(
                                    $record->form_data,
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                                );
                            })
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
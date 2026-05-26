<?php

namespace App\Filament\Resources\EnrolmentSubmissions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrolmentSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Submission ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('enrolment_id')
                    ->label('Enrolment')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('order_id')
                    ->label('Order')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('student_id')
                    ->label('Student')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('course.title')
                    ->label('Course')
                    ->limit(45)
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),

                TextColumn::make('plan')
                    ->label('Plan')
                    ->badge()
                    ->searchable(),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('id_document_path')
                    ->label('ID document')
                    ->placeholder('Not uploaded')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vet_transcript_path')
                    ->label('VET transcript')
                    ->placeholder('Not uploaded')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
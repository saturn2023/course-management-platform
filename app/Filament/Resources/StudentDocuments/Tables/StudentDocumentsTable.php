<?php

namespace App\Filament\Resources\StudentDocuments\Tables;

use App\Models\StudentDocument;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('original_name')
                    ->label('File')
                    ->state(fn (StudentDocument $record): ?string => $record->isFile()
                        ? ($record->original_name ?: basename($record->file_path))
                        : ($record->url ? 'Link: '.$record->url : null))
                    ->wrap()
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->date('d/m/Y'),
            ])
            ->defaultSort('sort_order')
            // Drag-and-drop ordering, same as ReviewsTable. Dragging rewrites
            // sort_order, the column StudentDocument::scopeActive() sorts by.
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                // Single-record delete fires the model's deleted event, which
                // removes the file from the private disk.
                DeleteAction::make(),
            ]);
    }
}

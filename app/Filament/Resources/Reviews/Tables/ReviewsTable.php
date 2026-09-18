<?php

namespace App\Filament\Resources\Reviews\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('reviewer_avatar')
                    ->label('Photo')
                    ->disk('public')
                    ->circular()
                    ->placeholder('-'),
                TextColumn::make('reviewer_name')
                    ->label('Reviewer')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('★', max(0, min(5, (int) $state))))
                    ->sortable(),
                TextColumn::make('review_date')
                    ->label('Review date')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('display_order')
            /*
             * Drag-and-drop ordering, built into Filament v4 — no extra package.
             * Dragging rewrites display_order, the same column the numeric field
             * and the homepage query use.
             */
            ->reorderable('display_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

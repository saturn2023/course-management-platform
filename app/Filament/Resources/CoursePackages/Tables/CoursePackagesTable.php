<?php

namespace App\Filament\Resources\CoursePackages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoursePackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_1')
                    ->label('Image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('title')
                    ->label('Title')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('codes')
                    ->label('Codes')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('show_on_homepage')
                    ->label('On homepage')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
            ])
            ->defaultSort('display_order')
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

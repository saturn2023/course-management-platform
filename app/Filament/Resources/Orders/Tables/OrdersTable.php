<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Jobs\ProcessOrderJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.id')
                    ->searchable(),
                TextColumn::make('subtotal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('xero_status')
                    ->searchable(),
                TextColumn::make('enrolment_status')
                    ->searchable(),
                TextColumn::make('xero_invoice_id')
                    ->searchable(),
                TextColumn::make('xero_invoice_number')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
->recordActions([
    Action::make('process_order')
        ->label('Process Order')
        ->icon('heroicon-o-play')
        ->color('success')
        ->requiresConfirmation()
        ->action(function ($record) {
            ProcessOrderJob::dispatch($record->id);

            Notification::make()
                ->title('Order processing started')
                ->body('Xero and enrolment jobs have been queued.')
                ->success()
                ->send();
        }),

    EditAction::make(),
])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

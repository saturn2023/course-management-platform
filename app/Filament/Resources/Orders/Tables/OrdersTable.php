<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Jobs\ProcessOrderJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.id')
                    ->label('Student')
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
                    ->label('Xero status')
                    ->badge()
                    ->searchable(),

                TextColumn::make('enrolment_status')
                    ->label('Enrolment status')
                    ->badge()
                    ->searchable(),

                TextColumn::make('xero_invoice_id')
                    ->label('Xero invoice ID')
                    ->limit(12)
                    ->copyable()
                    ->tooltip(fn ($record) => $record->xero_invoice_id)
                    ->searchable(),

                TextColumn::make('xero_invoice_number')
                    ->label('Xero invoice number')
                    ->searchable(),

                TextColumn::make('xero_sent_at')
                    ->label('Sent to Xero')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not sent'),

                TextColumn::make('xero_error_message')
                    ->label('Xero Error')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('-'),

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

                Action::make('view_xero_invoice')
                    ->label('View in Xero')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn ($record) => $record->xero_invoice_id
                        ? 'https://go.xero.com/AccountsReceivable/View.aspx?InvoiceID=' . $record->xero_invoice_id
                        : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => filled($record->xero_invoice_id)),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
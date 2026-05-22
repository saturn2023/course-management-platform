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
                TextColumn::make('id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('billing_company')
                    ->label('Company')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('billing_email')
                    ->label('Billing email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('students_count')
                    ->label('Students')
                    ->counts('students')
                    ->sortable(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Order status')
                    ->badge()
                    ->searchable(),

                TextColumn::make('xero_status')
                    ->label('Xero')
                    ->badge()
                    ->searchable(),

                TextColumn::make('enrolment_status')
                    ->label('Enrolment')
                    ->badge()
                    ->searchable(),

                TextColumn::make('xero_invoice_number')
                    ->label('Xero invoice')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Not created'),

                TextColumn::make('purchaser_confirmation_sent_at')
                    ->label('Purchaser email')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not sent'),

                TextColumn::make('xero_sent_at')
                    ->label('Sent to Xero')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not sent')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('xero_invoice_id')
                    ->label('Xero invoice ID')
                    ->limit(12)
                    ->copyable()
                    ->tooltip(fn ($record) => $record->xero_invoice_id)
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('xero_error_message')
                    ->label('Xero error')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

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
                Action::make('process_order')
                    ->label('Process Order')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'paid' && blank($record->xero_invoice_id))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        ProcessOrderJob::dispatch($record->id);

                        Notification::make()
                            ->title('Order processing started')
                            ->body('Xero, enrolment, and email jobs have been queued.')
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
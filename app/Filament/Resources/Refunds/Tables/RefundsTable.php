<?php

namespace App\Filament\Resources\Refunds\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Refund;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RefundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                /*
                 * The Xero invoice number is read through the order
                 * relationship rather than duplicated onto the refund.
                 */
                TextColumn::make('order.xero_invoice_number')
                    ->label('Xero invoice')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->placeholder('Not created'),

                TextColumn::make('order_id')
                    ->label('Order')
                    ->formatStateUsing(fn ($state): string => '#' . $state)
                    ->url(fn (Refund $record): string => OrderResource::getUrl('edit', ['record' => $record->order_id]))
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Refund amount')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('order.total')
                    ->label('Order total')
                    ->money('AUD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->searchable()
                    ->limit(60),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Refund::STATUSES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        Refund::STATUS_PROCESSED => 'success',
                        Refund::STATUS_PENDING => 'warning',
                        Refund::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Refunded date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Recorded by')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Refund::STATUSES),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_order')
                        ->label('View order')
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('info')
                        ->url(fn (Refund $record): string => OrderResource::getUrl('edit', ['record' => $record->order_id])),

                    EditAction::make(),

                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->tooltip('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

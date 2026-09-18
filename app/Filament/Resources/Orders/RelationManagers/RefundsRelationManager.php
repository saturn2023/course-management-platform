<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundNotAllowedException;
use App\Services\RefundRecorder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Refunds recorded against a single order, shown on the Order edit page.
 */
class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    protected static ?string $title = 'Refunds';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('amount')
                    ->label('Refund amount')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
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
                    }),

                TextColumn::make('createdBy.name')
                    ->label('Recorded by')
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Refunded date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                Action::make('record_refund')
                    ->label('Record refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->visible(fn (): bool => $this->getOwnerOrder()->remainingRefundable() > 0)
                    ->modalHeading(fn (): string => 'Record a refund for order #' . $this->getOwnerOrder()->id)
                    ->modalDescription('This records the refund in the admin only. No money is sent through Pin Payments, Xero, or any bank, and the order total is not changed.')
                    ->modalSubmitActionLabel('Record refund')
                    ->schema(fn (): array => [
                        TextInput::make('amount')
                            ->label('Refund amount')
                            ->prefix('A$')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($this->getOwnerOrder()->remainingRefundable())
                            ->default(number_format($this->getOwnerOrder()->remainingRefundable(), 2, '.', ''))
                            ->helperText(
                                'Remaining refundable: A$'
                                . number_format($this->getOwnerOrder()->remainingRefundable(), 2)
                                . ' of an order total of A$'
                                . number_format((float) $this->getOwnerOrder()->total, 2) . '.'
                            ),

                        TextInput::make('reason')
                            ->label('Refund reason')
                            ->required()
                            ->maxLength(255),

                        Select::make('status')
                            ->label('Status')
                            ->options(Refund::STATUSES)
                            ->default(Refund::STATUS_RECORDED)
                            ->required()
                            ->helperText('Leave as Recorded unless the money has genuinely been returned through the payment provider.'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->action(function (array $data, Action $action) {
                        $order = $this->getOwnerOrder();

                        try {
                            $refund = app(RefundRecorder::class)->record($order, $data, Auth::id());
                        } catch (RefundNotAllowedException $e) {
                            Notification::make()
                                ->title('Refund not recorded')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $order->refresh();

                        Notification::make()
                            ->title('Refund recorded')
                            ->body(
                                'A$' . number_format((float) $refund->amount, 2)
                                . ' recorded. Remaining refundable: A$'
                                . number_format($order->remainingRefundable(), 2)
                                . '. No money has been sent through a payment provider.'
                            )
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('amount')
                            ->label('Refund amount')
                            ->prefix('A$')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),

                        TextInput::make('reason')
                            ->label('Refund reason')
                            ->required()
                            ->maxLength(255),

                        Select::make('status')
                            ->label('Status')
                            ->options(Refund::STATUSES)
                            ->required(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->using(function (Refund $record, array $data, EditAction $action) {
                        try {
                            return app(RefundRecorder::class)->update($record, $data);
                        } catch (RefundNotAllowedException $e) {
                            Notification::make()
                                ->title('Refund not updated')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            $action->halt();

                            return $record;
                        }
                    }),

                DeleteAction::make(),
            ]);
    }

    private function getOwnerOrder(): Order
    {
        /** @var Order $order */
        $order = $this->getOwnerRecord();

        return $order;
    }
}

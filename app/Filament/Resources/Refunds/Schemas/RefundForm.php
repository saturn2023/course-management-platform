<?php

namespace App\Filament\Resources\Refunds\Schemas;

use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundNotAllowedException;
use App\Services\RefundRecorder;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RefundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->label('Original order')
                    ->relationship('order', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Order $record): string =>
                        '#' . $record->id
                        . ' — ' . ($record->xero_invoice_number ?: 'No Xero invoice')
                        . ' — A$' . number_format((float) $record->total, 2)
                        . ($record->billing_email ? ' — ' . $record->billing_email : '')
                    )
                    ->searchable(['id', 'xero_invoice_number', 'billing_email', 'billing_company'])
                    ->preload()
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                TextInput::make('amount')
                    ->label('Refund amount')
                    ->prefix('A$')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->helperText(function (Get $get, ?Model $record): string {
                        $order = Order::find($get('order_id'));

                        if (! $order) {
                            return 'Choose an order to see its remaining refundable balance.';
                        }

                        return 'Remaining refundable: A$'
                            . number_format($order->remainingRefundable($record?->getKey()), 2)
                            . ' of an order total of A$'
                            . number_format((float) $order->total, 2) . '.';
                    })
                    /*
                     * The same server-side rule the Orders row action uses, so
                     * combined refunds can never exceed the order total no
                     * matter which screen created them.
                     */
                    ->rule(static fn (Get $get, ?Model $record): Closure => static function (
                        string $attribute,
                        mixed $value,
                        Closure $fail
                    ) use ($get, $record): void {
                        $order = Order::find($get('order_id'));

                        if (! $order) {
                            return;
                        }

                        try {
                            RefundRecorder::assertAmountAllowed(
                                $order,
                                RefundRecorder::normaliseAmount($value),
                                $record?->getKey()
                            );
                        } catch (RefundNotAllowedException $e) {
                            $fail($e->getMessage());
                        }
                    }),

                Select::make('status')
                    ->label('Status')
                    ->options(Refund::STATUSES)
                    ->default(Refund::STATUS_RECORDED)
                    ->required()
                    ->helperText('Leave as Recorded unless the money has genuinely been returned through the payment provider.'),

                TextInput::make('reason')
                    ->label('Refund reason')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}

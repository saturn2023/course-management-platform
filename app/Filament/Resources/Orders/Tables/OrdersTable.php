<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Jobs\ProcessOrderJob;
use App\Jobs\SendEnrolmentEmailJob;
use App\Jobs\SendEnrolmentSmsJob;
use App\Jobs\SendPurchaserConfirmationEmailJob;
use App\Jobs\SendXeroInvoiceEmailJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Storage;
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
              TextColumn::make('purchase_order_number')
    ->label('PO number')
    ->searchable()
    ->copyable()
    ->placeholder('-')
    ->toggleable(isToggledHiddenByDefault: true),

TextColumn::make('payment_method')
    ->label('Payment method')
    ->badge()
    ->formatStateUsing(fn (?string $state): string => match ($state) {
        'purchase_order' => 'Purchase Order',
        'card' => 'Card',
        default => $state
            ? ucwords(str_replace('_', ' ', $state))
            : '-',
    })
    ->color(fn (?string $state): string => match ($state) {
        'purchase_order' => 'warning',
        'card' => 'success',
        default => 'gray',
    })
    ->placeholder('-'),

TextColumn::make('payment_status')
    ->label('Payment status')
    ->badge()
    ->formatStateUsing(fn (?string $state): string => $state
        ? ucwords(str_replace('_', ' ', $state))
        : '-'
    )
    ->color(fn (?string $state): string => match ($state) {
        'paid' => 'success',
        'pending' => 'warning',
        'failed' => 'danger',
        'refunded' => 'gray',
        default => 'gray',
    })
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
    ->color(fn (string $state): string => match ($state) {
        'paid' => 'success',
          'processing' => 'info',
        'po_pending' => 'warning',
        'pending' => 'gray',
        'cancelled' => 'danger',
        'failed' => 'danger',
        default => 'gray',
    })
    ->formatStateUsing(fn (string $state): string => match ($state) {
        'po_pending' => 'PO Pending',
        default => ucwords(str_replace('_', ' ', $state)),
    })
    ->searchable(),

                TextColumn::make('xero_status')
                    ->label('Xero')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('enrolment_status')
                    ->label('Enrolment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'link_sent' => 'success',
                        'completed' => 'success',
                        'link_created' => 'warning',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'gray',
                    })
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
   SelectFilter::make('status')
    ->label('Order status')
    ->options([
        'paid' => 'Paid',
        'processing' => 'Processing',
        'po_pending' => 'PO Pending',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
    ]),

    SelectFilter::make('payment_method')
        ->label('Payment method')
        ->options([
            'purchase_order' => 'Purchase Order',
            'card' => 'Card',
        ]),

    SelectFilter::make('payment_status')
        ->label('Payment status')
        ->options([
            'pending' => 'Pending',
            'paid' => 'Paid',
            'failed' => 'Failed',
        ]),
])
            ->recordActions([
Action::make('process_order')
    ->label('Process Order')
    ->icon('heroicon-o-play')
    ->color('success')
    ->visible(fn ($record): bool =>
        in_array($record->status, ['paid', 'po_pending'], true)
        && blank($record->xero_invoice_id)
    )
    ->requiresConfirmation()
    ->modalHeading(fn ($record): string =>
        $record->status === 'po_pending'
            ? 'Approve and process purchase order'
            : 'Process order'
    )
    ->modalDescription(fn ($record): string =>
        $record->status === 'po_pending'
            ? 'This will approve the purchase order, create a draft (unsent) Xero invoice for admin review, and create enrolment links for the students.'
            : 'This will create a draft (unsent) Xero invoice for admin review and create enrolment links for the order.'
    )
    ->action(function ($record) {
        if ($record->status === 'po_pending') {
            $record->update([
                'status' => 'processing',
            ]);
        }

        ProcessOrderJob::dispatch($record->id);

        Notification::make()
            ->title('Order processing started')
            ->body('Xero, enrolment, email, and SMS jobs have been queued where applicable.')
            ->success()
            ->send();
    }),

                Action::make('retry_order')
                    ->label('Retry Order')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn ($record) =>
                        $record->status === 'paid'
                        && (
                            $record->xero_status === 'failed'
                            || $record->enrolment_status === 'failed'
                            || $record->enrolment_status === 'link_created'
                        )
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Retry failed or incomplete order processing')
                    ->modalDescription('This will retry the incomplete parts of the order. Existing successful steps should be skipped by duplicate protection.')
                    ->action(function ($record) {
                        $record->refresh();

                        $enrolments = $record->enrolments()->get();

                        $shouldRunFullProcess = false;
                        $studentEmailsQueued = 0;
                        $studentSmsQueued = 0;
                        $purchaserEmailQueued = false;

                        if ($record->xero_status === 'failed' && blank($record->xero_invoice_id)) {
                            $record->update([
                                'xero_status' => 'pending',
                                'xero_error_message' => null,
                            ]);

                            $shouldRunFullProcess = true;
                        }

                        if ($record->enrolment_status === 'failed') {
                            $record->update([
                                'enrolment_status' => 'pending',
                            ]);

                            if ($enrolments->isEmpty()) {
                                $shouldRunFullProcess = true;
                            }
                        }

                        if ($enrolments->isNotEmpty()) {
                            foreach ($enrolments as $enrolment) {
                                SendEnrolmentEmailJob::dispatch($enrolment->id, true);
                                SendEnrolmentSmsJob::dispatch($enrolment->id, true);

                                $studentEmailsQueued++;
                                $studentSmsQueued++;
                            }

                            if (filled($record->billing_email)) {
                                SendPurchaserConfirmationEmailJob::dispatch($record->id, true);
                                $purchaserEmailQueued = true;
                            }
                        }

                        if ($shouldRunFullProcess || $enrolments->isEmpty()) {
                            ProcessOrderJob::dispatch($record->id);
                        }

                        Notification::make()
                            ->title('Retry started')
                            ->body(
                                'Retry jobs have been queued. '
                                . $studentEmailsQueued . ' student email(s) queued. '
                                . $studentSmsQueued . ' SMS job(s) queued. '
                                . ($purchaserEmailQueued ? 'Purchaser confirmation queued.' : '')
                            )
                            ->success()
                            ->send();
                    }),

                Action::make('resend_student_emails')
                    ->label('Resend student emails')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn ($record) => $record->enrolments()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Resend student enrolment emails')
                    ->modalDescription('This will resend enrolment link emails to all students attached to this order.')
                    ->action(function ($record) {
                        $enrolments = $record->enrolments()->get();

                        foreach ($enrolments as $enrolment) {
                            SendEnrolmentEmailJob::dispatch($enrolment->id, true);
                        }

                        Notification::make()
                            ->title('Student enrolment emails queued')
                            ->body($enrolments->count() . ' student email(s) have been queued for resend.')
                            ->success()
                            ->send();
                    }),

                Action::make('resend_student_sms')
                    ->label('Resend student SMS')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->visible(fn ($record) => $record->enrolments()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Resend student SMS')
                    ->modalDescription('This will send or resend enrolment SMS messages to all students attached to this order.')
                    ->action(function ($record) {
                        $enrolments = $record->enrolments()->get();

                        foreach ($enrolments as $enrolment) {
                            SendEnrolmentSmsJob::dispatch($enrolment->id, true);
                        }

                        Notification::make()
                            ->title('Student SMS messages queued')
                            ->body($enrolments->count() . ' SMS message(s) have been queued.')
                            ->success()
                            ->send();
                    }),

                Action::make('resend_purchaser_email')
                    ->label('Resend purchaser email')
                    ->icon('heroicon-o-envelope-open')
                    ->color('warning')
                    ->visible(fn ($record) => filled($record->billing_email))
                    ->requiresConfirmation()
                    ->modalHeading('Resend purchaser confirmation email')
                    ->modalDescription('This will resend the purchaser confirmation email to the billing email address.')
                    ->action(function ($record) {
                        SendPurchaserConfirmationEmailJob::dispatch($record->id, true);

                        Notification::make()
                            ->title('Purchaser confirmation email queued')
                            ->body('The purchaser confirmation email has been queued for resend.')
                            ->success()
                            ->send();
                    }),

                Action::make('resend_invoice')
                    ->label('Resend invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    // Hidden during the DRAFT testing phase so a draft invoice
                    // can never be emailed to a customer. Enabled in production
                    // via XERO_AUTO_EMAIL_INVOICE=true.
                    ->visible(fn ($record) =>
                        config('services.xero.auto_email_invoice', false)
                        && filled($record->xero_invoice_id)
                        && (filled($record->billing_email) || filled($record->student?->email))
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Resend Xero invoice')
                    ->modalDescription('This will re-fetch the official invoice PDF from Xero and email it to the billing email address.')
                    ->action(function ($record) {
                        SendXeroInvoiceEmailJob::dispatch($record->id, true);

                        Notification::make()
                            ->title('Invoice email queued')
                            ->body('The Xero invoice PDF has been queued for resend to the billing email.')
                            ->success()
                            ->send();
                    }),
                Action::make('download_purchase_order')
    ->label('Download PO document')
    ->icon('heroicon-o-arrow-down-tray')
    ->color('warning')
    ->visible(fn ($record): bool =>
        filled($record->purchase_order_document_path)
        && Storage::disk('local')->exists($record->purchase_order_document_path)
    )
    ->action(function ($record) {
        return Storage::disk('local')->download(
            $record->purchase_order_document_path,
            basename($record->purchase_order_document_path)
        );
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
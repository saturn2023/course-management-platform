<?php

namespace App\Filament\Resources\Refunds\Pages;

use App\Filament\Resources\Refunds\RefundResource;
use App\Models\Refund;
use App\Services\RefundNotAllowedException;
use App\Services\RefundRecorder;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRefund extends EditRecord
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Re-checks the refundable balance (ignoring this refund's own current
     * amount) before persisting the change.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Refund $record */
        try {
            return app(RefundRecorder::class)->update($record, $data);
        } catch (RefundNotAllowedException $e) {
            Notification::make()
                ->title('Refund not updated')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.amount' => $e->getMessage(),
            ]);
        }
    }
}

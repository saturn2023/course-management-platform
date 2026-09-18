<?php

namespace App\Filament\Resources\Refunds\Pages;

use App\Filament\Resources\Refunds\RefundResource;
use App\Models\Order;
use App\Services\RefundNotAllowedException;
use App\Services\RefundRecorder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateRefund extends CreateRecord
{
    protected static string $resource = RefundResource::class;

    /**
     * Refunds are always created through RefundRecorder so the locked
     * balance check runs server-side, whichever screen was used.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $order = Order::findOrFail($data['order_id']);

        try {
            return app(RefundRecorder::class)->record($order, $data, Auth::id());
        } catch (RefundNotAllowedException $e) {
            Notification::make()
                ->title('Refund not recorded')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.amount' => $e->getMessage(),
            ]);
        }
    }
}

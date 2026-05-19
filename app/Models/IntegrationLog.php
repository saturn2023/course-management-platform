<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    protected $fillable = [
        'order_id',
        'service',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
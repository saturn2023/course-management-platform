<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'student_id',
        'subtotal',
        'total',
        'status',
        'xero_status',
        'xero_sent_at',
        'enrolment_status',
        'xero_invoice_id',
        'xero_invoice_number',
        'xero_error_message',
    ];

    protected $casts = [
        'xero_sent_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }
}
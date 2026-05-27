<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CheckoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'course_code',
        'plan_id',
        'schedule_id',
        'quantity',
        'course_title',
        'plan_title',
        'start_date',
        'end_date',
        'dates',
        'delivery_mode',
        'unit_price',
        'subtotal',
        'stock_quantity',
        'enrolments',
        'rto_payload',
        'student_details',
        'billing_details',
        'details_completed_at',
        'expires_at',
        'completed_at',
        'order_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'dates' => 'array',
        'rto_payload' => 'array',
        'student_details' => 'array',
        'billing_details' => 'array',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'stock_quantity' => 'integer',
        'enrolments' => 'integer',
        'details_completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function hasSavedDetails(): bool
    {
        return $this->details_completed_at !== null;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'student_id',

        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_email',
        'billing_phone',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_postcode',
        'billing_abn',
        'purchaser_confirmation_sent_at',
        'subtotal',
        'total',
        'status',
        'xero_status',
        'enrolment_status',
        'xero_invoice_id',
        'xero_invoice_number',
        'xero_sent_at',
        'xero_error_message',
    ];

protected $casts = [
    'subtotal' => 'decimal:2',
    'total' => 'decimal:2',
    'xero_sent_at' => 'datetime',
    'purchaser_confirmation_sent_at' => 'datetime',
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

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function orderStudents(): HasMany
    {
        return $this->hasMany(OrderStudent::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'order_students')
            ->withTimestamps();
    }
}
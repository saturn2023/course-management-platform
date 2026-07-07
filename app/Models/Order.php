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

    'purchase_order_number',
    'purchase_order_document_path',
    'payment_method',
    'payment_status',

    'subtotal',
    'total',
    'status',
    'xero_status',
    'enrolment_status',

    'xero_invoice_id',
    'xero_invoice_number',
    'xero_sent_at',
    'xero_error_message',

    'invoice_sent_at',
    'invoice_email_status',
    'invoice_email_error',

    'purchaser_confirmation_sent_at',

    'pin_charge_token',
    'pin_charge_amount_cents',
    'card_scheme',
    'card_display_number',
    'paid_at',
];

protected $casts = [
    'subtotal' => 'decimal:2',
    'total' => 'decimal:2',
    'xero_sent_at' => 'datetime',
    'invoice_sent_at' => 'datetime',
    'purchaser_confirmation_sent_at' => 'datetime',
    'pin_charge_amount_cents' => 'integer',
    'paid_at' => 'datetime',
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

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
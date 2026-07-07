<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'can_use_purchase_order',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'can_use_purchase_order' => 'boolean',
        ];
    }

    /**
     * Administrators may use both card and purchase-order checkout.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * A purchase-order-only client may use purchase order but must never
     * reach card checkout. Admins are never PO-only.
     */
    public function isPurchaseOrderOnlyClient(): bool
    {
        return ! $this->isAdmin() && (bool) $this->can_use_purchase_order;
    }

    /**
     * Card is available to admins and to normal logged-in customers, but
     * never to PO-only clients. (Guests are handled outside the model.)
     */
    public function canPayByCard(): bool
    {
        return ! $this->isPurchaseOrderOnlyClient();
    }

    /**
     * Purchase order is available to admins and PO-only clients.
     */
    public function canPayByPurchaseOrder(): bool
    {
        return $this->isAdmin() || $this->isPurchaseOrderOnlyClient();
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
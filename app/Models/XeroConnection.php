<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XeroConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'tenant_name',
        'access_token',
        'refresh_token',
        'expires_at',
        'branding_theme_id',
        'branding_theme_name',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
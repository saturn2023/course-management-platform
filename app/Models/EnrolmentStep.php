<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EnrolmentStep extends Model
{
    protected $fillable = [
        'title',
        'content',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The steps the homepage shows: active only, in admin order. id is the
     * tie-breaker so equal display_order values still come out in a stable
     * order rather than whatever the database happens to return.
     */
    public function scopeForHomepage(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'title',
        'code',
        'slug',
        'description',
        'price',
        'status',
        'ams_enrolment_code',
         'ams_plan_id',

        // Frontend homepage card fields.
        'card_title',
        'image_path',
        'icon_path',
        'banner_text',
        'course_url',
        'display_order',
        'show_on_homepage',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'show_on_homepage' => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
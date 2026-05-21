<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrolment extends Model
{
    protected $fillable = [
        'order_id',
        'student_id',
        'course_id',
        'external_enrolment_id',
        'enrolment_link',
        'status',
        'error_message',
        'request_payload',
        'response_payload',
        'email_sent_at',
        'link_sent_at',
    ];
    protected $casts = [
    'email_sent_at' => 'datetime',
    'link_sent_at' => 'datetime',
];
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Enrolment extends Model
{
    protected $fillable = [
        'order_id',
        'student_id',
        'course_id',
        'external_enrolment_id',
        'enrolment_link',
        'enrolment_token',
        'enrolment_token_expires_at',
        'enrolment_completed_at',
        'status',
        'error_message',
        'request_payload',
        'response_payload',
        'email_sent_at',
        'link_sent_at',
        'sms_sent_at',
        'sms_error_message',
        'secret_key',
   'secret_base_url',
    ];

    protected $casts = [
        'email_sent_at' => 'datetime',
        'link_sent_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'enrolment_token_expires_at' => 'datetime',
        'enrolment_completed_at' => 'datetime',
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
    public function submission(): HasOne
    {
    return $this->hasOne(EnrolmentSubmission::class);
     }
}
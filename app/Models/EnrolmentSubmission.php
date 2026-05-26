<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrolmentSubmission extends Model
{
    protected $fillable = [
        'enrolment_id',
        'order_id',
        'student_id',
        'course_id',
        'code',
        'plan',
        'form_data',
        'id_document_path',
        'vet_transcript_path',
        'submitted_at',
        'api_status',
'api_attempts',
'api_last_attempted_at',
'api_submitted_at',
'api_error_message',
'api_request_payload',
'api_response_payload',
'external_reference',
    ];

    protected $casts = [
        'form_data' => 'array',
        'submitted_at' => 'datetime',
        'api_last_attempted_at' => 'datetime',
'api_submitted_at' => 'datetime',
'api_request_payload' => 'array',
'api_response_payload' => 'array',
    ];

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

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
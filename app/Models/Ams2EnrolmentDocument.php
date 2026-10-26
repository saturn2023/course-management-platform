<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document uploaded with an Enrolment v2 submission.
 *
 * `original_name` is the filename the learner chose, and is what RTO Data must
 * be sent — not the randomised storage name in `file_path`.
 *
 * Nothing writes to this table in stage 1; it exists so the upload step has its
 * shape fixed before anything depends on it.
 */
class Ams2EnrolmentDocument extends Model
{
    use HasFactory;

    protected $table = 'ams2_enrolment_documents';

    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RETRY = 'retry';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REPLACED = 'replaced';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Ams2Enrolment::class, 'enrolment_row');
    }
}

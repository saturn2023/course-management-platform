<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One link in the /student-information documents list. Managed at
 * Admin → Student Documents.
 *
 * Each row is either an uploaded file or an external URL, never both (the
 * admin form enforces that). Files live on the private `local` disk — not the
 * public disk, so nothing depends on storage:link — and are served by
 * StudentDocumentController through /student-information/documents/{slug}.
 * That URL is keyed on the slug, so it stays the same when the file is
 * replaced.
 */
class StudentDocument extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'student-documents';

    protected $fillable = [
        'title',
        'slug',
        'file_path',
        'original_name',
        'url',
        'sort_order',
        'is_active',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Keep the private disk in step with the table: a replaced or removed
     * file is deleted when the row saves, and a deleted row takes its file
     * with it. Filament does not clean up old uploads on its own.
     */
    protected static function booted(): void
    {
        static::saving(function (StudentDocument $document) {
            if (blank($document->file_path)) {
                $document->original_name = null;
            }
        });

        static::updated(function (StudentDocument $document) {
            $oldPath = $document->getOriginal('file_path');

            if ($document->wasChanged('file_path') && filled($oldPath)) {
                Storage::disk(self::DISK)->delete($oldPath);
            }
        });

        static::deleted(function (StudentDocument $document) {
            if (filled($document->file_path)) {
                Storage::disk(self::DISK)->delete($document->file_path);
            }
        });
    }

    /**
     * The documents the page shows: active only, in admin order. id is the
     * tie-breaker so equal sort_order values still come out in a stable order.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isFile(): bool
    {
        return filled($this->file_path);
    }

    public function isLink(): bool
    {
        return ! $this->isFile() && filled($this->url);
    }

    public function fileExists(): bool
    {
        return $this->isFile() && Storage::disk(self::DISK)->exists($this->file_path);
    }

    /**
     * Download name: the slugged title plus the original extension, e.g.
     * "Learner Handbook v3.2 26062023" → learner-handbook-v32-26062023.pdf.
     * Falls back to the stored file's extension if the original name is gone.
     */
    public function downloadFilename(): string
    {
        $extension = pathinfo((string) ($this->original_name ?: $this->file_path), PATHINFO_EXTENSION);
        $name = Str::slug($this->title) ?: 'document';

        return $extension === '' ? $name : $name.'.'.strtolower($extension);
    }
}

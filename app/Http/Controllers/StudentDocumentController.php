<?php

namespace App\Http\Controllers;

use App\Models\StudentDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDocumentController extends Controller
{
    /**
     * GET /student-information/documents/{studentDocument:slug}
     *
     * Streams the file from the private `local` disk. 404s for hidden
     * documents, link-only items, and rows whose file has gone missing, so a
     * stale link never leaks anything.
     */
    public function download(StudentDocument $studentDocument): StreamedResponse
    {
        abort_unless($studentDocument->is_active && $studentDocument->fileExists(), 404);

        return Storage::disk(StudentDocument::DISK)->download(
            $studentDocument->file_path,
            $studentDocument->downloadFilename(),
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Support\Facades\Storage;

class MeetingFileController extends Controller
{
    public function audio(Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        $file = $meeting->files()->where('kind', 'original')->firstOrFail();
        abort_if($file->deleted_at || ! Storage::disk($file->disk)->exists($file->path), 404);

        $contentType = match (strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION))) {
            'm4a', 'mp4' => 'audio/mp4',
            'mp3', 'mpga', 'mpeg' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            default => $file->mime_type,
        };

        return response()->file(Storage::disk($file->disk)->path($file->path), [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => "inline; filename*=UTF-8''".rawurlencode($file->original_name),
            'Content-Type' => $contentType,
        ]);
    }
}

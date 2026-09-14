<?php

namespace App\Services;

use App\Contracts\AudioInspector;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class FfprobeAudioInspector implements AudioInspector
{
    public function inspect(string $path): array
    {
        $result = Process::timeout(30)->run([
            config('meetings.ffprobe'), '-v', 'error', '-show_entries',
            'format=duration,format_name', '-of', 'json', $path,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('El archivo no contiene una pista de audio válida.');
        }

        $payload = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        $duration = (float) data_get($payload, 'format.duration', 0);
        if ($duration <= 0) {
            throw new RuntimeException('No fue posible determinar la duración del audio.');
        }

        return [
            'duration_ms' => (int) round($duration * 1000),
            'mime_type' => mime_content_type($path) ?: 'application/octet-stream',
        ];
    }
}

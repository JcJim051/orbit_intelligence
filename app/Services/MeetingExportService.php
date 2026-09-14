<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingSummary;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeetingExportService
{
    public function markdown(Meeting $meeting, MeetingSummary $summary): string
    {
        return view('exports.markdown', compact('meeting', 'summary'))->render();
    }

    public function pdf(Meeting $meeting, MeetingSummary $summary): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.pdf', compact('meeting', 'summary'))->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @return array{path:string,name:string,mime:string} */
    public function materialize(Meeting $meeting, MeetingSummary $summary, string $type): array
    {
        $base = "exports/{$meeting->id}/v".str_pad((string) $summary->version, 3, '0', STR_PAD_LEFT);

        return match ($type) {
            'audio' => $this->audioArtifact($meeting),
            'transcript' => $this->write($base.'/transcripcion.md', "# Transcripción\n\n".$meeting->transcript->edited_text, 'text/markdown'),
            'minutes_markdown' => $this->write($base.'/acta.md', $this->markdown($meeting, $summary), 'text/markdown'),
            'minutes_pdf' => $this->write($base.'/acta.pdf', $this->pdf($meeting, $summary), 'application/pdf'),
            'manifest' => $this->write($base.'/manifest.json', $this->manifest($meeting, $summary), 'application/json'),
            default => throw new \InvalidArgumentException('Artefacto desconocido.'),
        };
    }

    private function write(string $path, string $contents, string $mime): array
    {
        Storage::disk('local')->put($path, $contents);

        return ['path' => Storage::disk('local')->path($path), 'name' => basename($path), 'mime' => $mime];
    }

    private function audioArtifact(Meeting $meeting): array
    {
        $file = $meeting->files()->where('kind', 'original')->firstOrFail();

        return ['path' => Storage::disk($file->disk)->path($file->path), 'name' => 'audio-original.'.pathinfo($file->original_name, PATHINFO_EXTENSION), 'mime' => $file->mime_type];
    }

    private function manifest(Meeting $meeting, MeetingSummary $summary): string
    {
        $files = [];
        foreach ($meeting->artifacts as $type) {
            $artifact = $this->materialize($meeting, $summary, $type);
            $files[] = [
                'type' => $type,
                'name' => $artifact['name'],
                'size_bytes' => filesize($artifact['path']),
                'sha256' => hash_file('sha256', $artifact['path']),
            ];
        }

        return json_encode([
            'meeting_id' => $meeting->id,
            'title' => $meeting->title,
            'held_at' => $meeting->held_at->toIso8601String(),
            'version' => $summary->version,
            'approved_at' => $summary->approved_at?->toIso8601String(),
            'artifacts' => $meeting->artifacts,
            'original_sha256' => $meeting->files()->where('kind', 'original')->value('sha256'),
            'files' => $files,
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function folder(Meeting $meeting): string
    {
        return $meeting->held_at->format('Y/m/').$meeting->held_at->format('Y-m-d').'_'.Str::slug($meeting->title).'_'.$meeting->id;
    }
}

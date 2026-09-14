<?php

namespace App\Console\Commands;

use App\Models\MeetingFile;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneMeetingAudioCommand extends Command
{
    protected $signature = 'meetings:prune-audio {--dry-run}';

    protected $description = 'Elimina audios originales aprobados según AUDIO_RETENTION_DAYS';

    public function handle(AuditLogger $audit): int
    {
        $days = config('meetings.retention_days');
        if ($days === null || $days === '') {
            $this->info('Retención desactivada; no se eliminó ningún audio.');

            return self::SUCCESS;
        }
        $files = MeetingFile::query()->with('meeting')->where('kind', 'original')->whereNull('deleted_at')
            ->whereHas('meeting', fn ($q) => $q->whereNotNull('approved_at')->where('approved_at', '<=', now()->subDays((int) $days)))->get();
        foreach ($files as $file) {
            if (! $this->option('dry-run')) {
                Storage::disk($file->disk)->delete($file->path);
                $file->update(['deleted_at' => now()]);
                $audit->log($file->meeting, 'audio.deleted_by_retention', null, ['sha256' => $file->sha256, 'retention_days' => (int) $days]);
            }
            $this->line(($this->option('dry-run') ? '[simulado] ' : '').$file->path);
        }
        $this->info($files->count().' archivo(s) evaluados.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\RefreshOpenDataSource;
use App\Models\OpenDataSource;
use Illuminate\Console\Command;

class RefreshOpenDataSources extends Command
{
    protected $signature = 'open-data:refresh {--source=} {--queue}';

    protected $description = 'Actualiza las instantáneas de las fuentes publicadas de Datos.gov.co';

    public function handle(): int
    {
        $query = OpenDataSource::query()->whereHas('layer.viewers', fn ($query) => $query->where('status', 'published'));
        if ($id = $this->option('source')) {
            $query->where(fn ($query) => $query->where('id', $id)->orWhere('slug', $id));
        }
        $sources = $query->get();
        foreach ($sources as $source) {
            $job = new RefreshOpenDataSource($source->id);
            $this->option('queue') ? dispatch($job) : dispatch_sync($job);
            $this->line("Programada: {$source->name}");
        }
        $this->info("Fuentes procesadas: {$sources->count()}");

        return self::SUCCESS;
    }
}

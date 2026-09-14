<?php

namespace App\Services\Postgis;

use RuntimeException;
use Symfony\Component\Process\Process;

class LocalPostgisRuntime
{
    public function start(): void
    {
        $process = new Process([
            'docker',
            'compose',
            '-f',
            'compose.postgis.yml',
            'up',
            '-d',
            '--wait',
        ], base_path());
        $process->setTimeout(240);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Docker no pudo iniciar PostGIS: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}

<?php

namespace App\Observers;

use App\Services\Micrositio\ContadoresMicrositio;
use Illuminate\Database\Eloquent\Model;

class InvalidaContadoresMicrositio
{
    public function saved(Model $registro): void
    {
        ContadoresMicrositio::olvidar();
    }

    public function deleted(Model $registro): void
    {
        ContadoresMicrositio::olvidar();
    }

    public function restored(Model $registro): void
    {
        ContadoresMicrositio::olvidar();
    }
}

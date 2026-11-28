<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SiidEquivalenciaArea extends Model
{
    protected $table = 'siid_publicacion.equivalencias_area';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(SiidAreaTematica::class, 'area_tematica_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->texto_normalizado = Str::of($model->texto_origen)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
            $model->actualizado_por = auth()->id();
            $model->creado_por ??= auth()->id();
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MicrositioMapaCapa extends Model
{
    protected $table = 'micrositio_mapa_capas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valores' => 'array',
            'valor_departamental' => 'float',
            'valor_anterior_departamental' => 'float',
            'fuente_actualizada_en' => 'datetime',
            'sincronizada_en' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    public function ultimaSincronizacion(): HasOne
    {
        return $this->hasOne(MicrositioMapaSincronizacion::class, 'capa_codigo', 'codigo')->latestOfMany();
    }

    /** @return array<string, mixed> */
    public function comoCapaPublica(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'rotulo_corto' => $this->rotulo_corto,
            'unidad' => $this->unidad,
            'unidad_etiqueta' => $this->unidad_etiqueta,
            'periodo' => $this->periodo,
            'fuente' => $this->fuente,
            'fuente_url' => $this->fuente_url,
            'fuente_dataset_id' => $this->fuente_dataset_id,
            'fuente_actualizada_en' => $this->fuente_actualizada_en?->toIso8601String(),
            'sincronizada_en' => $this->sincronizada_en?->toIso8601String(),
            'estado_frescura' => $this->estado,
            'polaridad' => $this->polaridad,
            'valor_departamental' => $this->valor_departamental,
            'valor_anterior_departamental' => $this->valor_anterior_departamental,
            'nota' => $this->nota,
            'valores' => array_values($this->valores ?? []),
            'decimales' => $this->decimales,
        ];
    }
}

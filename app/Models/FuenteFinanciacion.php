<?php

namespace App\Models;

use App\Enums\GrupoFuenteFinanciacion;
use Database\Factories\FuenteFinanciacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuenteFinanciacion extends Model
{
    /** @use HasFactory<FuenteFinanciacionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'fuentes_financiacion';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'grupo_reporte',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grupo_reporte' => GrupoFuenteFinanciacion::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * Grupo con el que se presentan los techos al sector. Si el catálogo aún no lo tiene, se usa la sugerencia del tipo oficial.
     */
    public function grupo(): GrupoFuenteFinanciacion
    {
        return $this->grupo_reporte ?? GrupoFuenteFinanciacion::sugerir($this->codigo, $this->tipo, $this->nombre);
    }

    public function etiqueta(): string
    {
        return $this->codigo.' — '.$this->nombre;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use Database\Factories\ActividadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Actividad de un proyecto a cargo de una dependencia. Se puede amarrar a una meta producto
 * (la estructura del plan no se muestra al sector; se usa en los reportes).
 */
class Actividad extends Model
{
    /** @use HasFactory<ActividadFactory> */
    use HasFactory, PerteneceADependencia, SoftDeletes;

    protected $table = 'actividades';

    protected $fillable = [
        'proyecto_id',
        'dependencia_id',
        'meta_producto_id',
        'codigo',
        'nombre',
        'unidad_medida',
        'cantidad_programada',
        'activo',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_programada' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function metaProducto(): BelongsTo
    {
        return $this->belongsTo(MetaProducto::class);
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ActividadProgramacion::class);
    }

    public function etiqueta(): string
    {
        return ($this->codigo ?: 'ACT-'.$this->id).' — '.$this->nombre;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoTrazadorCategoria extends Model
{
    protected $table = 'proyecto_trazador_categorias';

    protected $fillable = ['proyecto_trazador_id', 'trazador_categoria_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function proyectoTrazador(): BelongsTo
    {
        return $this->belongsTo(ProyectoTrazador::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(TrazadorCategoria::class, 'trazador_categoria_id');
    }

    public function distribuciones(): HasMany
    {
        return $this->hasMany(ReporteProyectoTrazadorDistribucion::class);
    }
}

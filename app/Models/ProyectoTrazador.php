<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoTrazador extends Model
{
    protected $table = 'proyecto_trazadores';

    protected $fillable = ['proyecto_id', 'trazador_id', 'politica_publica_departamental_id', 'activo', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function trazador(): BelongsTo
    {
        return $this->belongsTo(Trazador::class);
    }

    public function politicaPublica(): BelongsTo
    {
        return $this->belongsTo(PoliticaPublicaDepartamental::class, 'politica_publica_departamental_id');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(ProyectoTrazadorCategoria::class);
    }

    public function distribuciones(): HasMany
    {
        return $this->hasMany(ReporteProyectoTrazadorDistribucion::class);
    }
}

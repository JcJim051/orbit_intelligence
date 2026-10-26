<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trazador extends Model
{
    use SoftDeletes;

    protected $table = 'trazadores';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo'];

    protected static function booted(): void
    {
        static::creating(function (Trazador $trazador): void {
            if (filled($trazador->codigo)) {
                return;
            }

            $siguiente = ((int) self::withTrashed()->pluck('codigo')->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))->max()) + 1;
            $trazador->codigo = sprintf('TD-%03d', $siguiente);
        });
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(TrazadorCategoria::class);
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(ProyectoTrazador::class);
    }

    public function etiqueta(): string
    {
        return $this->codigo.' — '.$this->nombre;
    }
}

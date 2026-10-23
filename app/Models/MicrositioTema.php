<?php

namespace App\Models;

use Database\Factories\MicrositioTemaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MicrositioTema extends Model
{
    /** @use HasFactory<MicrositioTemaFactory> */
    use HasFactory;

    protected $table = 'micrositio_temas';

    protected $fillable = [
        'slug',
        'nombre',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(MicrositioIndicador::class)->orderBy('orden')->orderBy('id');
    }

    public function etiqueta(): string
    {
        return $this->slug.' — '.$this->nombre;
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'slug' => $this->slug,
            'nombre' => $this->nombre,
            'orden' => (int) $this->orden,
        ];
    }
}

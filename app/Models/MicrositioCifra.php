<?php

namespace App\Models;

use Database\Factories\MicrositioCifraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MicrositioCifra extends Model
{
    /** @use HasFactory<MicrositioCifraFactory> */
    use HasFactory;

    protected $table = 'micrositio_cifras';

    protected $fillable = [
        'valor',
        'descripcion',
        'fuente',
        'fecha_corte',
        'es_ejemplo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_corte' => 'date',
            'es_ejemplo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'valor' => $this->valor,
            'descripcion' => $this->descripcion,
            'fuente' => $this->fuente,
            'fecha_corte' => $this->fecha_corte?->toDateString(),
            'es_ejemplo' => (bool) $this->es_ejemplo,
            'orden' => (int) $this->orden,
        ];
    }
}

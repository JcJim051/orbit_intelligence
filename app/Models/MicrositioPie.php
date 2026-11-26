<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioPie extends Model
{
    protected $table = 'micrositio_pies';

    protected $fillable = [
        'direccion',
        'horario',
        'conmutador',
        'linea_gratuita',
        'anticorrupcion',
        'correo',
        'judiciales',
    ];

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'direccion' => $this->direccion,
            'horario' => $this->horario,
            'conmutador' => $this->conmutador,
            'linea_gratuita' => $this->linea_gratuita,
            'anticorrupcion' => $this->anticorrupcion,
            'correo' => $this->correo,
            'judiciales' => $this->judiciales,
        ];
    }
}

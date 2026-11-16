<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MicrositioMapa extends Model
{
    protected $table = 'micrositio_mapas';

    protected $fillable = [
        'mostrar_bloque',
        'capa_principal',
        'capas_adicionales',
        'periodo_modo',
        'periodo_fijo',
        'paleta',
        'polaridad',
        'texto_tarjeta',
        'texto_cta',
        'url_cta',
        'url_ficha_tecnica',
        'url_indicadores_municipio',
    ];

    protected $attributes = [
        'mostrar_bloque' => true,
        'capa_principal' => 'NBI-2018',
        'periodo_modo' => 'ultimo',
        'paleta' => 'auto',
        'polaridad' => 'auto',
        'texto_tarjeta' => '29 municipios · capital Villavicencio',
        'texto_cta' => 'Explorar en el visor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mostrar_bloque' => 'boolean',
            'capas_adicionales' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function atributosPublicables(): array
    {
        return [
            'mostrar_bloque' => (bool) $this->mostrar_bloque,
            'capa_principal' => $this->capa_principal,
            'capas_adicionales' => array_values($this->capas_adicionales ?? []),
            'periodo_modo' => $this->periodo_modo,
            'periodo_fijo' => $this->periodo_fijo,
            'paleta' => $this->paleta,
            'polaridad' => $this->polaridad,
            'texto_tarjeta' => $this->texto_tarjeta,
            'texto_cta' => $this->texto_cta,
            'url_cta' => $this->url_cta,
            'url_ficha_tecnica' => $this->url_ficha_tecnica,
            'url_indicadores_municipio' => $this->url_indicadores_municipio,
        ];
    }
}

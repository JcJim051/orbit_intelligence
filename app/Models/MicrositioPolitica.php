<?php

namespace App\Models;

use Database\Factories\MicrositioPoliticaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MicrositioPolitica extends Model
{
    /** @use HasFactory<MicrositioPoliticaFactory> */
    use HasFactory;

    protected $table = 'micrositio_politicas';

    protected $fillable = ['slug', 'nombre', 'descripcion', 'objetivo', 'ordenanza', 'vigencia', 'responsable', 'orden', 'es_ejemplo', 'documentos', 'nombres_documentos'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'es_ejemplo' => 'boolean', 'documentos' => 'array', 'nombres_documentos' => 'array'];
    }

    public function atributosPublicables(): array
    {
        return $this->only($this->getFillable());
    }
}

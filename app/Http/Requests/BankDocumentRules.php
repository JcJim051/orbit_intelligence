<?php

namespace App\Http\Requests;

class BankDocumentRules
{
    /**
     * @return array<int, mixed>
     */
    public static function archivo(bool $obligatorio): array
    {
        return [
            'bail',
            $obligatorio ? 'required' : 'nullable',
            'file',
            'mimes:pdf,doc,docx',
            'extensions:pdf,doc,docx',
            'max:10240',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $campo, string $nombre): array
    {
        return [
            $campo.'.required' => 'Adjunte '.$nombre.'.',
            $campo.'.file' => 'Adjunte un archivo válido.',
            $campo.'.uploaded' => 'No se pudo subir el archivo. Verifique que no supere los 10 MB e intente nuevamente.',
            $campo.'.mimes' => 'El archivo debe ser PDF o Word.',
            $campo.'.extensions' => 'Use un archivo PDF o Word con su extensión original.',
            $campo.'.max' => 'El archivo no puede superar los 10 MB.',
        ];
    }
}

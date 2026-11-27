<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'secretariat' => ['required', 'string', Rule::in(config('bank_requests.secretariats'))],
            'subject' => ['required', 'string', 'max:200'],
            'official_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'bank_file' => ['bail', 'required', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'secretariat.required' => 'Ingrese la secretaría.',
            'secretariat.in' => 'Seleccione una secretaría o dependencia del listado.',
            'secretariat.max' => 'La secretaría no puede superar los 150 caracteres.',
            'subject.required' => 'Ingrese el asunto de la solicitud.',
            'subject.max' => 'El asunto no puede superar los 200 caracteres.',
            'official_name.required' => 'Ingrese el nombre del funcionario.',
            'official_name.max' => 'El nombre del funcionario no puede superar los 150 caracteres.',
            'email.required' => 'Ingrese el correo electrónico.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar los 254 caracteres.',
            'bank_file.required' => 'Adjunte el archivo del banco.',
            'bank_file.file' => 'Adjunte un archivo válido.',
            'bank_file.uploaded' => 'No se pudo subir el archivo. Verifique que no supere los 10 MB e intente nuevamente.',
            'bank_file.mimes' => 'El archivo debe ser PDF o Word.',
            'bank_file.extensions' => 'Use un archivo PDF o Word con su extensión original.',
            'bank_file.max' => 'El archivo no puede superar los 10 MB.',
        ];
    }
}

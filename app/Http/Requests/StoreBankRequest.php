<?php

namespace App\Http\Requests;

use App\Services\Bancos\RadicacionBanco;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:200'],
            'official_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'bank_file' => BankDocumentRules::archivo(true),
            'annex_file' => BankDocumentRules::archivo(false),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $dependencia = app(RadicacionBanco::class)->identificar((string) $this->input('codigo'));
            if ($dependencia === null) {
                $validator->errors()->add('codigo', 'No encontramos una dependencia activa con ese código. Verifique e intente de nuevo.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'Ingrese el código de la dependencia.',
            'codigo.max' => 'El código no puede superar los 20 caracteres.',
            'subject.required' => 'Ingrese el asunto de la solicitud.',
            'subject.max' => 'El asunto no puede superar los 200 caracteres.',
            'official_name.required' => 'Ingrese el nombre del funcionario.',
            'official_name.max' => 'El nombre del funcionario no puede superar los 150 caracteres.',
            'email.required' => 'Ingrese el correo electrónico.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar los 254 caracteres.',
            ...BankDocumentRules::messages('bank_file', 'el formato'),
            ...BankDocumentRules::messages('annex_file', 'el anexo'),
        ];
    }
}

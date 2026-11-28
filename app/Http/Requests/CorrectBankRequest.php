<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_seguimiento' => ['required', 'string', 'max:20'],
            'bank_file' => BankDocumentRules::archivo(true),
            'annex_file' => BankDocumentRules::archivo(false),
        ];
    }

    public function messages(): array
    {
        return [
            'numero_seguimiento.required' => 'Ingrese el número de seguimiento.',
            'numero_seguimiento.max' => 'El número de seguimiento no puede superar los 20 caracteres.',
            ...BankDocumentRules::messages('bank_file', 'el formato corregido'),
            ...BankDocumentRules::messages('annex_file', 'el anexo'),
        ];
    }
}

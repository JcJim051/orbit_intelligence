<?php

namespace Database\Factories;

use App\Enums\BankRequestStatus;
use App\Models\BankRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BankRequest>
 */
class BankRequestFactory extends Factory
{
    protected $model = BankRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => (string) Str::uuid(),
            'numero_seguimiento' => 'BP-'.now()->year.'-'.strtoupper(fake()->unique()->bothify('######')),
            'secretariat' => 'Secretaría de Educación',
            'subject' => 'Solicitud de banco',
            'official_name' => 'Funcionario de prueba',
            'email' => 'funcionario@example.com',
            'status' => BankRequestStatus::Recibido,
            'file_path' => 'bank-requests/privado.pdf',
            'original_filename' => 'banco.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 100,
        ];
    }
}

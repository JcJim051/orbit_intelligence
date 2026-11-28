<?php

namespace Tests\Feature;

use App\Models\BankRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BankRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_request_page_is_public_and_prioritizes_the_request_form(): void
    {
        $this->get(route('banks.create'))->assertOk()
            ->assertSee('Solicitar banco')->assertSee('Secretaría')
            ->assertSee('Tipo de trámite')->assertSee('Asunto')->assertSee('Nombre del funcionario')->assertSee('Correo electrónico')
            ->assertSee('name="bank_file"', false)->assertDontSee('name="lookup_email"', false);
    }

    public function test_status_has_a_separate_public_page(): void
    {
        $this->get(route('banks.status.create'))->assertOk()
            ->assertSee('Correo electrónico o número de seguimiento')
            ->assertDontSee('name="bank_file"', false)->assertDontSee('Volver a solicitar banco');
    }

    #[TestWith(['project_registration'])]
    #[TestWith(['bank_certificate'])]
    #[TestWith(['technical_opinion'])]
    #[TestWith(['budget_certificate'])]
    public function test_guest_can_submit_a_request_with_a_private_file(string $procedureType): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('banco.pdf', "%PDF-1.4\nBanco de prueba\n%%EOF");

        $response = $this->post(route('banks.store'), [
            'secretariat' => 'Secretaría de Educación',
            'procedure_type' => $procedureType, 'subject' => 'Solicitud de banco', 'official_name' => 'Funcionario de prueba',
            'email' => 'funcionario@example.com',
            'bank_file' => $file,
        ]);

        $response->assertRedirectToRoute('banks.create')->assertSessionHas('bank_request_reference');
        $record = BankRequest::sole();
        $this->assertSame('Secretaría de Educación', $record->secretariat);
        $this->assertSame('Funcionario de prueba', $record->official_name);
        $this->assertSame('Solicitud de banco', $record->subject);
        $this->assertSame($procedureType, $record->procedure_type);
        $this->post(route('banks.status'), ['lookup' => $record->reference])
            ->assertSessionHas('bank_status_results.0.procedure_type', config('bank_requests.procedure_types')[$procedureType]);
        $this->assertSame('funcionario@example.com', $record->email);
        $this->assertSame('banco.pdf', $record->original_filename);
        $this->assertSame('received', $record->status);
        Storage::disk('local')->assertExists($record->file_path);
        $this->get('/storage/'.$record->file_path)->assertForbidden();
    }

    #[TestWith(['xls', 'application/vnd.ms-excel'])]
    #[TestWith(['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])]
    #[TestWith(['ppt', 'application/vnd.ms-powerpoint'])]
    #[TestWith(['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'])]
    public function test_excel_and_powerpoint_files_are_rejected(string $extension, string $mime): void
    {
        Storage::fake('local');
        $this->post(route('banks.store'), [
            'secretariat' => 'Secretaría de Educación', 'procedure_type' => 'project_registration', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.'.$extension, 10, $mime),
        ])->assertSessionHasErrors(['bank_file' => 'El archivo debe ser PDF o Word.']);
        $this->assertDatabaseCount('bank_requests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[TestWith(['doc', 'application/msword'])]
    #[TestWith(['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])]
    public function test_word_files_are_accepted(string $extension, string $mime): void
    {
        Storage::fake('local');
        $this->post(route('banks.store'), [
            'secretariat' => 'Secretaría de Salud', 'procedure_type' => 'project_registration', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.'.$extension, 10, $mime),
        ])->assertRedirectToRoute('banks.create')->assertSessionHas('bank_request_reference');
        $record = BankRequest::sole();
        $this->assertSame('banco.'.$extension, $record->original_filename);
        Storage::disk('local')->assertExists($record->file_path);
    }

    public function test_subject_cannot_exceed_two_hundred_characters(): void
    {
        $this->post(route('banks.store'), [
            'secretariat' => 'Secretaría de Educación', 'procedure_type' => 'project_registration', 'subject' => str_repeat('a', 201),
            'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['subject' => 'El asunto no puede superar los 200 caracteres.']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_required_fields_are_validated_without_creating_a_request(): void
    {
        $this->from(route('banks.create'))->post(route('banks.store'), [])
            ->assertRedirectToRoute('banks.create')
            ->assertSessionHasErrors(['secretariat', 'procedure_type', 'subject', 'official_name', 'email', 'bank_file']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_disguised_file_and_invalid_email_are_rejected(): void
    {
        Storage::fake('local');
        $temporaryFile = tempnam(sys_get_temp_dir(), 'bank-test-');
        file_put_contents($temporaryFile, '<?php echo 1;');
        $file = new UploadedFile($temporaryFile, 'banco.pdf', null, null, true);
        try {
            $this->post(route('banks.store'), [
                'secretariat' => 'Secretaría de Educación', 'procedure_type' => 'project_registration', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'invalido',
                'bank_file' => $file,
            ])->assertSessionHasErrors(['bank_file', 'email']);
        } finally {
            unlink($temporaryFile);
        }
        $this->assertDatabaseCount('bank_requests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_files_larger_than_ten_megabytes_are_rejected(): void
    {
        $this->post(route('banks.store'), [
            'secretariat' => 'Secretaría de Educación', 'procedure_type' => 'project_registration', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors(['bank_file' => 'El archivo no puede superar los 10 MB.']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_dependency_outside_the_list_is_rejected(): void
    {
        $this->post(route('banks.store'), [
            'secretariat' => 'Dependencia no permitida', 'procedure_type' => 'project_registration', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['secretariat' => 'Seleccione una secretaría o dependencia del listado.']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_status_rejects_an_invalid_lookup(): void
    {
        $this->post(route('banks.status'), ['lookup' => 'incorrecto'])
            ->assertSessionHasErrors(['lookup'], null, 'lookup')
            ->assertSessionMissing('bank_status_results');
    }

    public function test_status_requires_a_lookup(): void
    {
        $this->post(route('banks.status'), [])
            ->assertSessionHasErrors(['lookup'], null, 'lookup')
            ->assertSessionMissing('bank_status_results');
    }

    #[TestWith(['a49d9e66-8791-41da-8dfd-b42d19976f9e'])]
    #[TestWith(['PRUEBA@example.com'])]
    public function test_status_accepts_either_reference_or_email(string $lookup): void
    {
        BankRequest::create([
            'reference' => 'a49d9e66-8791-41da-8dfd-b42d19976f9e',
            'secretariat' => 'Educación', 'procedure_type' => 'project_registration', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'file_path' => 'bank-requests/privado.pdf', 'original_filename' => 'banco.pdf',
            'file_mime_type' => 'application/pdf', 'file_size' => 100,
        ]);

        $this->post(route('banks.status'), ['lookup' => 'otro@example.com'])
            ->assertSessionHasErrors(['lookup'], null, 'lookup')
            ->assertSessionMissing('bank_status_results');
        $this->post(route('banks.status'), ['lookup' => $lookup])
            ->assertRedirectToRoute('banks.status.create')
            ->assertSessionHas('bank_status_results.0.status', 'Recibida');
        $this->get(route('banks.status.create'))->assertOk()
            ->assertSee('banco.pdf')->assertSee('Educación')->assertSee('Seguimiento')
            ->assertDontSee('bank-requests/privado.pdf');
    }

    public function test_email_lookup_returns_multiple_requests_without_other_emails(): void
    {
        foreach (['prueba@example.com', 'prueba@example.com', 'otro@example.com'] as $index => $email) {
            BankRequest::create([
                'reference' => (string) Str::uuid(),
                'secretariat' => 'Educación', 'procedure_type' => 'project_registration', 'subject' => 'Banco '.$index, 'official_name' => 'Prueba', 'email' => $email,
                'file_path' => 'bank-requests/privado.pdf', 'original_filename' => 'banco.pdf',
                'file_mime_type' => 'application/pdf', 'file_size' => 100,
            ]);
        }
        $response = $this->post(route('banks.status'), ['lookup' => 'prueba@example.com']);
        $response->assertSessionHas('bank_status_results', fn (array $results): bool => count($results) === 2
            && $results[0]['subject'] === 'Banco 1' && $results[1]['subject'] === 'Banco 0');
    }

    public function test_procedure_type_outside_the_list_is_rejected(): void
    {
        Storage::fake('local');
        $this->post(route('banks.store'), [
            'secretariat' => 'Secretaría de Educación', 'procedure_type' => 'otro',
            'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['procedure_type' => 'Seleccione uno de los tipos de trámite del listado.']);
        $this->assertDatabaseCount('bank_requests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_legacy_requests_without_procedure_type_remain_consultable(): void
    {
        $record = BankRequest::create([
            'reference' => (string) Str::uuid(), 'secretariat' => 'Secretaría de Educación',
            'subject' => 'Solicitud anterior', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'file_path' => 'bank-requests/anterior.pdf', 'original_filename' => 'anterior.pdf',
            'file_mime_type' => 'application/pdf', 'file_size' => 100,
        ]);
        $this->post(route('banks.status'), ['lookup' => $record->reference])
            ->assertSessionHas('bank_status_results.0.procedure_type', 'No registrado');
        $this->get(route('banks.status.create'))->assertOk()->assertSee('No registrado')->assertSee('Solicitud anterior');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BankRequestFileKind;
use App\Enums\BankRequestStatus;
use App\Enums\UserRole;
use App\Mail\Bancos\EstadoActualizado;
use App\Mail\Bancos\ResumenPorCorreo;
use App\Mail\Bancos\SolicitudRecibida;
use App\Models\BankRequest;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\Bancos\RadicacionBanco;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BankRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_request_page_starts_by_asking_for_the_dependency_code(): void
    {
        $this->get(route('banks.create'))->assertOk()
            ->assertSee('Solicitar banco')
            ->assertSee('Código de la dependencia')
            ->assertSee('name="codigo"', false)
            ->assertDontSee('name="bank_file"', false)
            ->assertDontSee('name="lookup"', false);
    }

    public function test_a_valid_code_reveals_the_dependency_and_the_form(): void
    {
        $this->dependencia();

        $this->post(route('banks.identify'), ['codigo' => 'edu-2026'])
            ->assertRedirectToRoute('banks.create')
            ->assertSessionHas('bank_codigo', 'EDU-2026');

        $this->get(route('banks.create'))->assertOk()
            ->assertSee('Secretaría de Educación')
            ->assertSee('Asunto')->assertSee('Nombre del funcionario')->assertSee('Correo electrónico')
            ->assertSee('name="bank_file"', false)
            ->assertSee('name="annex_file"', false);
    }

    public function test_status_has_a_separate_public_page(): void
    {
        $this->get(route('banks.status.create'))->assertOk()
            ->assertSee('Número de seguimiento o correo electrónico')
            ->assertDontSee('Volver a solicitar banco');
    }

    public function test_guest_can_submit_a_request_with_a_private_file_and_receives_the_tracking_number_by_email(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->dependencia();

        $response = $this->post(route('banks.store'), [
            'codigo' => 'EDU-2026',
            'subject' => 'Solicitud de banco',
            'official_name' => 'Funcionario de prueba',
            'email' => 'Funcionario@example.com',
            'bank_file' => UploadedFile::fake()->createWithContent('banco.pdf', "%PDF-1.4\nBanco de prueba\n%%EOF"),
            'annex_file' => UploadedFile::fake()->create('anexo.pdf', 20, 'application/pdf'),
        ]);

        $response->assertRedirectToRoute('banks.create')->assertSessionHas('bank_request_reference');
        $record = BankRequest::sole();
        $this->assertMatchesRegularExpression('/^BP-\d{4}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $record->numero_seguimiento);
        $this->assertSame(session('bank_request_reference'), $record->numero_seguimiento);
        $this->assertSame('Secretaría de Educación', $record->secretariat);
        $this->assertSame('Funcionario de prueba', $record->official_name);
        $this->assertSame('Solicitud de banco', $record->subject);
        $this->assertSame('funcionario@example.com', $record->email);
        $this->assertSame(BankRequestStatus::Recibido, $record->status);
        $this->assertSame('banco.pdf', $record->original_filename);
        Storage::disk('local')->assertExists($record->file_path);
        $this->assertSame(1, $record->files()->where('kind', BankRequestFileKind::Anexo)->count());
        $this->get('/storage/'.$record->file_path)->assertForbidden();
        Mail::assertSent(SolicitudRecibida::class, fn (SolicitudRecibida $mail): bool => $mail->hasTo('funcionario@example.com'));

        $mail = new SolicitudRecibida($record);
        $mail->assertSeeInHtml($record->numero_seguimiento);
        $mail->assertSeeInHtml(route('banks.status.create'));
    }

    #[TestWith(['xls', 'application/vnd.ms-excel'])]
    #[TestWith(['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])]
    #[TestWith(['ppt', 'application/vnd.ms-powerpoint'])]
    #[TestWith(['pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'])]
    public function test_excel_and_powerpoint_files_are_rejected(string $extension, string $mime): void
    {
        Storage::fake('local');
        $this->dependencia();
        $this->post(route('banks.store'), [
            'codigo' => 'EDU-2026', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
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
        $this->dependencia('Secretaría de Salud', 'SALUD-26');
        $this->post(route('banks.store'), [
            'codigo' => 'SALUD-26', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.'.$extension, 10, $mime),
        ])->assertRedirectToRoute('banks.create')->assertSessionHas('bank_request_reference');
        $record = BankRequest::sole();
        $this->assertSame('banco.'.$extension, $record->original_filename);
        Storage::disk('local')->assertExists($record->file_path);
    }

    public function test_subject_cannot_exceed_two_hundred_characters(): void
    {
        $this->dependencia();
        $this->post(route('banks.store'), [
            'codigo' => 'EDU-2026', 'subject' => str_repeat('a', 201),
            'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['subject' => 'El asunto no puede superar los 200 caracteres.']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_required_fields_are_validated_without_creating_a_request(): void
    {
        $this->from(route('banks.create'))->post(route('banks.store'), [])
            ->assertRedirectToRoute('banks.create')
            ->assertSessionHasErrors(['codigo', 'subject', 'official_name', 'email', 'bank_file']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_disguised_file_and_invalid_email_are_rejected(): void
    {
        Storage::fake('local');
        $this->dependencia();
        $temporaryFile = tempnam(sys_get_temp_dir(), 'bank-test-');
        file_put_contents($temporaryFile, '<?php echo 1;');
        $file = new UploadedFile($temporaryFile, 'banco.pdf', null, null, true);
        try {
            $this->post(route('banks.store'), [
                'codigo' => 'EDU-2026', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'invalido',
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
        $this->dependencia();
        $this->post(route('banks.store'), [
            'codigo' => 'EDU-2026', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors(['bank_file' => 'El archivo no puede superar los 10 MB.']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_unknown_or_inactive_dependency_code_is_rejected(): void
    {
        $this->dependencia()->update(['activo' => false]);

        $this->post(route('banks.store'), [
            'codigo' => 'EDU-2026', 'subject' => 'Solicitud de banco', 'official_name' => 'Prueba', 'email' => 'prueba@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['codigo' => 'No encontramos una dependencia activa con ese código. Verifique e intente de nuevo.']);

        $this->post(route('banks.identify'), ['codigo' => 'NO-EXISTE'])
            ->assertSessionHasErrors(['codigo' => 'No encontramos una dependencia activa con ese código. Verifique e intente de nuevo.']);
        $this->assertDatabaseCount('bank_requests', 0);
    }

    public function test_status_rejects_an_invalid_lookup(): void
    {
        $this->post(route('banks.status'), ['lookup' => 'incorrecto'])
            ->assertSessionHasErrors(['lookup'], null, 'lookup')
            ->assertSessionMissing('bank_consulta_numero');
    }

    public function test_status_requires_a_lookup(): void
    {
        $this->post(route('banks.status'), [])
            ->assertSessionHasErrors(['lookup'], null, 'lookup')
            ->assertSessionMissing('bank_consulta_numero');
    }

    public function test_status_by_tracking_number_shows_only_that_request(): void
    {
        $solicitud = BankRequest::factory()->create([
            'numero_seguimiento' => 'BP-2026-AB23CD',
            'secretariat' => 'Educación',
            'subject' => 'Solicitud de banco',
            'original_filename' => 'banco.pdf',
            'file_path' => 'bank-requests/privado.pdf',
        ]);
        $solicitud->events()->create(['to_status' => BankRequestStatus::Recibido]);

        $this->post(route('banks.status'), ['lookup' => 'bp-2026-ab23cd'])
            ->assertRedirectToRoute('banks.status.create');
        $this->get(route('banks.status.create'))->assertOk()
            ->assertSee('BP-2026-AB23CD')->assertSee('banco.pdf')->assertSee('Educación')->assertSee('Seguimiento')
            ->assertSee('Recibido')
            ->assertDontSee('bank-requests/privado.pdf');
    }

    public function test_email_lookup_sends_the_status_and_does_not_list_documents(): void
    {
        Mail::fake();
        BankRequest::factory()->create([
            'numero_seguimiento' => 'BP-2026-PRIMERO',
            'email' => 'prueba@example.com',
            'subject' => 'Banco visible',
        ]);
        BankRequest::factory()->create([
            'numero_seguimiento' => 'BP-2026-SEGUNDO',
            'email' => 'prueba@example.com',
            'subject' => 'Banco también',
        ]);
        BankRequest::factory()->create([
            'numero_seguimiento' => 'BP-2026-OTRO',
            'email' => 'otro@example.com',
            'subject' => 'Banco ajeno',
        ]);

        $this->post(route('banks.status'), ['lookup' => 'PRUEBA@example.com'])
            ->assertRedirectToRoute('banks.status.create')
            ->assertSessionHas('bank_status_notice');
        $this->get(route('banks.status.create'))->assertOk()
            ->assertSee('Si ese correo tiene solicitudes, le enviamos el estado y el número de seguimiento.')
            ->assertDontSee('Banco visible')
            ->assertDontSee('BP-2026-PRIMERO')
            ->assertDontSee('Banco ajeno');
        Mail::assertSent(ResumenPorCorreo::class, function (ResumenPorCorreo $mail): bool {
            return $mail->hasTo('prueba@example.com')
                && $mail->solicitudes->pluck('numero_seguimiento')->sort()->values()->all() === ['BP-2026-PRIMERO', 'BP-2026-SEGUNDO'];
        });
    }

    public function test_returned_request_can_be_corrected_on_the_same_tracking_number(): void
    {
        Mail::fake();
        Storage::fake('local');
        $dependencia = $this->dependencia();
        $this->post(route('banks.store'), [
            'codigo' => 'EDU-2026',
            'subject' => 'Solicitud de banco',
            'official_name' => 'Funcionario de prueba',
            'email' => 'funcionario@example.com',
            'bank_file' => UploadedFile::fake()->create('banco.pdf', 10, 'application/pdf'),
            'annex_file' => UploadedFile::fake()->create('anexo.pdf', 10, 'application/pdf'),
        ])->assertRedirectToRoute('banks.create');

        $solicitud = BankRequest::sole();
        $revisor = User::factory()->create(['role' => UserRole::BankReviewer]);
        $revisor->dependencias()->attach($dependencia);
        app(RadicacionBanco::class)->cambiarEstado($solicitud, BankRequestStatus::Devuelto, $revisor, 'Falta la firma del secretario.');

        $this->post(route('banks.status'), ['lookup' => $solicitud->numero_seguimiento]);
        $this->get(route('banks.status.create'))->assertOk()
            ->assertSee('Devuelto')
            ->assertSee('result-status-devuelto', false)
            ->assertSee('Falta la firma del secretario.')
            ->assertSee('Enviar la corrección')
            ->assertSee('bank-correction', false)
            ->assertSee('result-mark-devuelto', false);
        Mail::assertSent(EstadoActualizado::class);

        $this->post(route('banks.correct'), [
            'numero_seguimiento' => $solicitud->numero_seguimiento,
            'bank_file' => UploadedFile::fake()->create('corregido.pdf', 12, 'application/pdf'),
        ])->assertRedirectToRoute('banks.status.create');

        $solicitud->refresh();
        $this->assertSame(BankRequestStatus::Recibido, $solicitud->status);
        $this->assertSame('corregido.pdf', $solicitud->original_filename);
        $this->assertSame(2, $solicitud->files()->where('kind', BankRequestFileKind::Formato)->count());
        $this->assertSame('anexo.pdf', $solicitud->archivoActual(BankRequestFileKind::Anexo)?->original_filename);
        $this->get(route('banks.status.create'))->assertSee('Recibido')->assertSee('result-status-recibido', false)->assertSee('corregido.pdf');

        $this->post(route('banks.correct'), [
            'numero_seguimiento' => $solicitud->numero_seguimiento,
            'bank_file' => UploadedFile::fake()->create('otra.pdf', 12, 'application/pdf'),
        ])->assertSessionHasErrors(['numero_seguimiento'], null, 'correction');
        $this->assertSame(2, $solicitud->files()->where('kind', BankRequestFileKind::Formato)->count());
    }

    private function dependencia(string $nombre = 'Secretaría de Educación', string $codigo = 'EDU-2026'): Dependencia
    {
        return Dependencia::factory()->create([
            'nombre' => $nombre,
            'codigo_radicacion' => $codigo,
            'activo' => true,
        ]);
    }
}

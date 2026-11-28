<?php

namespace Tests\Feature;

use App\Enums\BankRequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\BankRequestResource;
use App\Filament\Resources\BankRequestResource\Pages\ViewBankRequest;
use App\Mail\Bancos\EstadoActualizado;
use App\Models\BankRequest;
use App\Models\BankRequestFile;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\Bancos\RadicacionBanco;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BankRequestReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('management'));
    }

    public function test_reviewer_home_opens_the_assigned_inbox_without_module_cards(): void
    {
        $dependencia = Dependencia::factory()->create();
        $revisor = $this->revisor($dependencia);
        $this->solicitud($dependencia, 'BP-2026-BANDEJA');

        $this->actingAs($revisor)->get('/gestion')
            ->assertRedirect(BankRequestResource::getUrl());

        $this->actingAs($revisor)->get('/gestion/solicitudes-banco')
            ->assertOk()
            ->assertSee('BP-2026-BANDEJA')
            ->assertDontSee('Módulos disponibles')
            ->assertDontSee('Abrir módulo')
            ->assertDontSee('Consultar actas');
    }

    public function test_reviewer_only_sees_requests_from_assigned_dependencies(): void
    {
        [$propia, $ajena] = $this->dependencias();
        $revisor = $this->revisor($propia);
        $visible = $this->solicitud($propia, 'BP-2026-PROPIA');
        $oculta = $this->solicitud($ajena, 'BP-2026-AJENA');

        $this->actingAs($revisor)->get('/gestion/solicitudes-banco')->assertOk()
            ->assertSee('BP-2026-PROPIA')
            ->assertDontSee('BP-2026-AJENA');
        $this->actingAs($revisor)->get('/gestion/solicitudes-banco/'.$visible->id)->assertOk();
        $this->actingAs($revisor)->get('/gestion/solicitudes-banco/'.$oculta->id)->assertNotFound();
    }

    public function test_investment_manager_sees_unassigned_requests_and_a_member_cannot_open_the_inbox(): void
    {
        $dependencia = Dependencia::factory()->create();
        $this->solicitud($dependencia, 'BP-2026-ASIGNADA');
        $huerfana = BankRequest::factory()->create([
            'numero_seguimiento' => 'BP-2026-HUERFANA',
            'dependencia_id' => null,
        ]);
        $gerente = User::factory()->create(['role' => UserRole::InvestmentManager]);
        $miembro = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($gerente)->get('/gestion/solicitudes-banco')->assertOk()
            ->assertSee('BP-2026-ASIGNADA')
            ->assertSee('BP-2026-HUERFANA');
        $this->actingAs($gerente)->get('/gestion/solicitudes-banco/'.$huerfana->id)->assertOk();
        $this->actingAs($miembro)->get('/gestion/solicitudes-banco')->assertForbidden();
    }

    public function test_reviewer_can_return_a_request_and_the_secretariat_is_notified(): void
    {
        Mail::fake();
        $dependencia = Dependencia::factory()->create();
        $revisor = $this->revisor($dependencia);
        $solicitud = $this->solicitud($dependencia, 'BP-2026-DEVUELTA');

        Livewire::actingAs($revisor)
            ->test(ViewBankRequest::class, ['record' => $solicitud->getKey()])
            ->assertSee('Documento recibido')
            ->assertSee('Decisión')
            ->assertSee('Trazabilidad')
            ->callAction('devolver', data: [
                'observacion' => 'Falta la firma del secretario.',
            ]);

        $solicitud->refresh();
        $this->assertSame(BankRequestStatus::Devuelto, $solicitud->status);
        $this->assertSame('Falta la firma del secretario.', $solicitud->events()->latest('id')->first()?->observation);
        Mail::assertSent(EstadoActualizado::class, fn (EstadoActualizado $mail): bool => $mail->hasTo('funcionario@example.com')
            && $mail->observacion === 'Falta la firma del secretario.');
    }

    public function test_a_return_without_a_useful_observation_is_rejected(): void
    {
        $dependencia = Dependencia::factory()->create();
        $revisor = $this->revisor($dependencia);
        $solicitud = $this->solicitud($dependencia, 'BP-2026-CORTA');

        $this->expectException(ValidationException::class);
        try {
            app(RadicacionBanco::class)->cambiarEstado($solicitud, BankRequestStatus::Devuelto, $revisor, 'corta');
        } finally {
            $this->assertSame(BankRequestStatus::Recibido, $solicitud->refresh()->status);
        }
    }

    public function test_only_the_assigned_reviewer_can_download_the_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bank-requests/privado.pdf', 'formato');
        [$propia, $ajena] = $this->dependencias();
        $revisor = $this->revisor($propia);
        $ajeno = $this->revisor($ajena);
        $solicitud = $this->solicitud($propia, 'BP-2026-ARCHIVO');
        $archivo = BankRequestFile::query()->create([
            'bank_request_id' => $solicitud->id,
            'version' => 1,
            'kind' => 'formato',
            'file_path' => 'bank-requests/privado.pdf',
            'original_filename' => 'banco.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 7,
        ]);

        $this->actingAs($revisor)->get(route('banks.files.download', $archivo))->assertOk();
        $this->actingAs($ajeno)->get(route('banks.files.download', $archivo))->assertNotFound();
    }

    public function test_a_guest_cannot_download_a_bank_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bank-requests/privado.pdf', 'formato');
        $dependencia = Dependencia::factory()->create();
        $solicitud = $this->solicitud($dependencia, 'BP-2026-INVITADO');
        $archivo = BankRequestFile::query()->create([
            'bank_request_id' => $solicitud->id,
            'version' => 1,
            'kind' => 'formato',
            'file_path' => 'bank-requests/privado.pdf',
            'original_filename' => 'banco.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 7,
        ]);

        $this->get(route('banks.files.download', $archivo))
            ->assertRedirect(route('login'));
        $this->get(route('banks.files.download', ['archivo' => $archivo, 'inline' => 1]))
            ->assertRedirect(route('login'));
    }

    public function test_reviewer_previews_a_pdf_without_replacing_the_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bank-requests/formato.pdf', "%PDF-1.4\n");
        $dependencia = Dependencia::factory()->create();
        $revisor = $this->revisor($dependencia);
        $solicitud = $this->solicitud($dependencia, 'BP-2026-VISTA');
        $archivo = BankRequestFile::query()->create([
            'bank_request_id' => $solicitud->id,
            'version' => 1,
            'kind' => 'formato',
            'file_path' => 'bank-requests/formato.pdf',
            'original_filename' => 'banco.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 9,
        ]);

        $vista = $this->actingAs($revisor)->get(route('banks.files.download', ['archivo' => $archivo, 'inline' => 1]));
        $vista->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $vista->headers->get('Content-Disposition'));
        $this->get(route('banks.files.download', $archivo))->assertDownload('banco.pdf');
        $this->get('/gestion/solicitudes-banco/'.$solicitud->id)
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee('Descargar')
            ->assertSee('data-pdf-preview', false);

        Storage::disk('local')->put('bank-requests/formato.pdf', '<html></html>');
        $this->get(route('banks.files.download', ['archivo' => $archivo, 'inline' => 1]))->assertStatus(415);

        $archivo->update([
            'original_filename' => 'banco.docx',
            'file_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        $this->get(route('banks.files.download', ['archivo' => $archivo, 'inline' => 1]))->assertStatus(415);
        $this->get('/gestion/solicitudes-banco/'.$solicitud->id)
            ->assertOk()
            ->assertDontSee('data-pdf-preview', false)
            ->assertSee('Descargar');
    }

    public function test_only_management_can_assign_filing_codes(): void
    {
        $dependencia = Dependencia::factory()->create(['nombre' => 'Secretaría de Hacienda']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $revisor = $this->revisor($dependencia);

        $this->actingAs($admin)->get('/gestion/codigos-radicacion')->assertOk()
            ->assertSee('Secretaría de Hacienda');
        $this->actingAs($revisor)->get('/gestion/codigos-radicacion')->assertForbidden();

        app(RadicacionBanco::class)->guardarCodigo($dependencia, 'hda-2026');
        $this->assertSame('HDA-2026', $dependencia->refresh()->codigo_radicacion);

        $otra = Dependencia::factory()->create();
        $this->expectException(ValidationException::class);
        app(RadicacionBanco::class)->guardarCodigo($otra, 'HDA-2026');
    }

    private function revisor(Dependencia $dependencia): User
    {
        $revisor = User::factory()->create(['role' => UserRole::BankReviewer]);
        $revisor->dependencias()->attach($dependencia);

        return $revisor;
    }

    private function solicitud(Dependencia $dependencia, string $numero): BankRequest
    {
        return BankRequest::factory()->create([
            'dependencia_id' => $dependencia->id,
            'numero_seguimiento' => $numero,
            'secretariat' => $dependencia->nombre,
            'email' => 'funcionario@example.com',
        ]);
    }

    /**
     * @return array{0: Dependencia, 1: Dependencia}
     */
    private function dependencias(): array
    {
        return [
            Dependencia::factory()->create(['nombre' => 'Secretaría de Educación']),
            Dependencia::factory()->create(['nombre' => 'Secretaría de Salud']),
        ];
    }
}

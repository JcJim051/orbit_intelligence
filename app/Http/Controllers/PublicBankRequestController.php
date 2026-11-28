<?php

namespace App\Http\Controllers;

use App\Enums\BankRequestFileKind;
use App\Enums\BankRequestStatus;
use App\Http\Requests\CorrectBankRequest;
use App\Http\Requests\StoreBankRequest;
use App\Mail\Bancos\ResumenPorCorreo;
use App\Models\BankRequest;
use App\Services\Bancos\RadicacionBanco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class PublicBankRequestController extends Controller
{
    public function __construct(private RadicacionBanco $radicacion) {}

    public function create(Request $request): View
    {
        if ($request->boolean('cambiar')) {
            $request->session()->forget('bank_codigo');
        }

        $codigo = old('codigo');
        if (! is_string($codigo) || $codigo === '') {
            $codigo = $request->session()->get('bank_codigo');
        }

        $dependencia = is_string($codigo) ? $this->radicacion->identificar($codigo) : null;

        return view('banks.create', [
            'dependencia' => $dependencia,
            'codigo' => $dependencia ? $this->radicacion->normalizarCodigo((string) $codigo) : null,
        ]);
    }

    public function identify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
        ], [
            'codigo.required' => 'Ingrese el código de la dependencia.',
            'codigo.max' => 'El código no puede superar los 20 caracteres.',
        ]);

        $codigo = $this->radicacion->normalizarCodigo($validated['codigo']);
        if ($this->radicacion->identificar($codigo) === null) {
            return to_route('banks.create')->withErrors([
                'codigo' => 'No encontramos una dependencia activa con ese código. Verifique e intente de nuevo.',
            ])->withInput($request->only('codigo'));
        }

        $request->session()->put('bank_codigo', $codigo);

        return to_route('banks.create');
    }

    public function store(StoreBankRequest $request): RedirectResponse
    {
        $dependencia = $this->radicacion->identificar((string) $request->validated('codigo'));
        abort_unless($dependencia, 422);

        $solicitud = $this->radicacion->radicar(
            $dependencia,
            $request->validated('subject'),
            $request->validated('official_name'),
            $request->validated('email'),
            $request->file('bank_file'),
            $request->file('annex_file'),
        );

        $request->session()->forget('bank_codigo');

        return to_route('banks.create')->with('bank_request_reference', $solicitud->numero_seguimiento);
    }

    public function lookup(Request $request): View
    {
        $numero = $request->session()->get('bank_consulta_numero');
        $resultado = null;

        if (is_string($numero) && $numero !== '') {
            $solicitud = BankRequest::query()
                ->with(['events', 'files'])
                ->where('numero_seguimiento', $numero)
                ->first();

            if ($solicitud instanceof BankRequest) {
                $resultado = $this->resultado($solicitud);
            }
        }

        return view('banks.status', ['resultado' => $resultado]);
    }

    public function status(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('lookup', [
            'lookup' => ['required', 'string', 'max:254'],
        ], [
            'lookup.required' => 'Ingrese el número de seguimiento o su correo electrónico.',
            'lookup.max' => 'El dato de consulta no puede superar los 254 caracteres.',
        ]);

        $lookup = trim($validated['lookup']);

        if (filter_var($lookup, FILTER_VALIDATE_EMAIL)) {
            $solicitudes = BankRequest::query()
                ->where('email', strtolower($lookup))
                ->latest('id')
                ->limit(20)
                ->get();

            if ($solicitudes->isNotEmpty()) {
                try {
                    Mail::to(strtolower($lookup))->send(new ResumenPorCorreo($solicitudes));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $request->session()->forget('bank_consulta_numero');

            return to_route('banks.status.create')->with(
                'bank_status_notice',
                'Si ese correo tiene solicitudes, le enviamos el estado y el número de seguimiento.',
            );
        }

        $solicitud = BankRequest::query()
            ->where('numero_seguimiento', strtoupper($lookup))
            ->first();

        if ($solicitud === null) {
            $request->session()->forget('bank_consulta_numero');

            return to_route('banks.status.create')->withErrors([
                'lookup' => 'No encontramos una solicitud con ese número de seguimiento.',
            ], 'lookup')->withInput($request->only('lookup'));
        }

        $request->session()->put('bank_consulta_numero', $solicitud->numero_seguimiento);

        return to_route('banks.status.create')->withInput($request->only('lookup'));
    }

    public function correct(CorrectBankRequest $request): RedirectResponse
    {
        $solicitud = BankRequest::query()
            ->where('numero_seguimiento', strtoupper(trim($request->validated('numero_seguimiento'))))
            ->first();

        if ($solicitud === null || $solicitud->status !== BankRequestStatus::Devuelto) {
            return to_route('banks.status.create')->withErrors([
                'numero_seguimiento' => 'Ese número no admite una corrección en este momento.',
            ], 'correction');
        }

        $this->radicacion->corregir(
            $solicitud,
            $request->file('bank_file'),
            $request->file('annex_file'),
        );

        $request->session()->put('bank_consulta_numero', $solicitud->numero_seguimiento);

        return to_route('banks.status.create');
    }

    /**
     * @return array<string, mixed>
     */
    private function resultado(BankRequest $solicitud): array
    {
        $formato = $solicitud->archivoActual(BankRequestFileKind::Formato);
        $anexo = $solicitud->archivoActual(BankRequestFileKind::Anexo);
        $observacion = $solicitud->events
            ->where('to_status', BankRequestStatus::Devuelto)
            ->sortByDesc('id')
            ->first()
            ?->observation;

        return [
            'reference' => $solicitud->numero_seguimiento,
            'status' => $solicitud->status->label(),
            'status_value' => $solicitud->status->value,
            'secretariat' => $solicitud->secretariat,
            'subject' => $solicitud->subject,
            'filename' => $formato->original_filename ?? $solicitud->original_filename,
            'annex_filename' => $anexo?->original_filename,
            'file_size' => round(($formato->file_size ?? $solicitud->file_size) / 1024 / 1024, 2),
            'received_at' => $solicitud->created_at->timezone(config('app.timezone'))->format('d/m/Y, H:i'),
            'can_correct' => $solicitud->status === BankRequestStatus::Devuelto,
            'observacion' => $solicitud->status === BankRequestStatus::Devuelto ? $observacion : null,
            'historial' => $solicitud->events->sortBy('id')->map(fn ($evento): array => [
                'estado' => $evento->to_status->label(),
                'estado_valor' => $evento->to_status->value,
                'cuando' => $evento->created_at->timezone(config('app.timezone'))->format('d/m/Y, H:i'),
                'observacion' => $evento->observation,
            ])->values()->all(),
        ];
    }
}

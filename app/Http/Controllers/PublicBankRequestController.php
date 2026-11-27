<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Models\BankRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PublicBankRequestController extends Controller
{
    public function create(): View
    {
        return view('banks.create', [
            'secretariats' => config('bank_requests.secretariats'),
            'procedureTypes' => config('bank_requests.procedure_types'),
        ]);
    }

    public function store(StoreBankRequest $request): RedirectResponse
    {
        $file = $request->file('bank_file');
        $path = $file->store('bank-requests', 'local');

        abort_unless($path, 503, 'No fue posible guardar el archivo. Intente nuevamente.');

        try {
            $bankRequest = BankRequest::create([
                ...$request->safe()->only(['secretariat', 'procedure_type', 'subject', 'official_name', 'email']),
                'email' => strtolower($request->validated('email')),
                'reference' => (string) Str::uuid(),
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'file_mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return to_route('banks.create')->with('bank_request_reference', $bankRequest->reference);
    }

    public function lookup(): View
    {
        return view('banks.status');
    }

    public function status(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('lookup', [
            'lookup' => ['required', 'string', 'max:254'],
        ], [
            'lookup.required' => 'Ingrese su correo electrónico o número de seguimiento.',
            'lookup.max' => 'El dato de consulta no puede superar los 254 caracteres.',
        ]);

        $lookup = trim($validated['lookup']);
        $isEmail = filter_var($lookup, FILTER_VALIDATE_EMAIL) !== false;
        if (! $isEmail && ! Str::isUuid($lookup)) {
            return to_route('banks.status.create')->withErrors([
                'lookup' => 'Ingrese un correo electrónico válido o el número de seguimiento completo.',
            ], 'lookup')->withInput($request->only('lookup'));
        }

        $records = BankRequest::query()
            ->where($isEmail ? 'email' : 'reference', strtolower($lookup))
            ->latest('id')->limit(20)->get();

        if ($records->isEmpty()) {
            return to_route('banks.status.create')->withErrors([
                'lookup' => 'No encontramos solicitudes con ese dato. Verifique el correo o número de seguimiento.',
            ], 'lookup')->withInput($request->only('lookup'));
        }

        $results = $records->map(fn (BankRequest $record): array => [
            'reference' => $record->reference,
            'status' => match ($record->status) {
                'in_review' => 'En revisión',
                'completed' => 'Finalizada',
                default => 'Recibida',
            },
            'secretariat' => $record->secretariat,
            'subject' => $record->subject,
            'procedure_type' => config('bank_requests.procedure_types')[$record->procedure_type] ?? 'No registrado',
            'filename' => $record->original_filename,
            'file_size' => round($record->file_size / 1024 / 1024, 2),
            'received_at' => $record->created_at->format('d/m/Y, H:i'),
        ])->all();

        return to_route('banks.status.create')->with('bank_status_results', $results)
            ->withInput($request->only('lookup'));
    }
}

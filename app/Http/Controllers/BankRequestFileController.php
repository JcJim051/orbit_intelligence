<?php

namespace App\Http\Controllers;

use App\Models\BankRequestFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankRequestFileController extends Controller
{
    public function __invoke(Request $request, BankRequestFile $archivo): StreamedResponse
    {
        $user = auth()->user();
        $solicitud = $archivo->bankRequest;
        abort_unless($user instanceof User && $solicitud && $user->can('view', $solicitud), 404);
        abort_unless(Storage::disk('local')->exists($archivo->file_path), 404);

        if ($request->boolean('inline')) {
            abort_unless($this->contenidoEsPdf($archivo), 415);

            return Storage::disk('local')->response(
                $archivo->file_path,
                $archivo->original_filename,
                ['Content-Type' => 'application/pdf'],
                'inline',
            );
        }

        return Storage::disk('local')->download($archivo->file_path, $archivo->original_filename);
    }

    private function contenidoEsPdf(BankRequestFile $archivo): bool
    {
        if (! $archivo->esPdf()) {
            return false;
        }

        $origen = Storage::disk('local')->readStream($archivo->file_path);
        if (! is_resource($origen)) {
            return false;
        }

        $cabecera = stream_get_contents($origen, 5);
        fclose($origen);

        return is_string($cabecera) && str_starts_with($cabecera, '%PDF-');
    }
}

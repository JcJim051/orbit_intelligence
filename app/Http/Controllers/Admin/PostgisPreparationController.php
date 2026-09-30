<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Postgis\PrepareManagedPostgis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PostgisPreparationController extends Controller
{
    public function __invoke(Request $request, PrepareManagedPostgis $preparation): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        try {
            $preparation->handle();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'PostgreSQL fue verificado, recibió las migraciones pendientes y actualizó las capas publicadas sin reemplazar los datos existentes.');
    }
}

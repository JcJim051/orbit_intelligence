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

        return back()->with('status', 'PostgreSQL fue migrado, recibió los datos de SQLite y materializó las capas publicadas. Ya puede activar el cambio final.');
    }
}

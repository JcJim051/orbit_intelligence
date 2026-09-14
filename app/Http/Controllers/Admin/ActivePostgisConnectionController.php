<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ActivePostgisConnectionController extends Controller
{
    public function store(Request $request, ManagedPostgisConfiguration $configuration): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        try {
            $configuration->activate();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('login')->with('status', 'PostgreSQL quedó activo. Inicie sesión nuevamente para verificar la plataforma.');
    }

    public function destroy(Request $request, ManagedPostgisConfiguration $configuration): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $configuration->deactivate();

        return redirect()->route('login')->with('status', 'La plataforma volvió a SQLite. Inicie sesión nuevamente.');
    }
}

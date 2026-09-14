<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PublishDriveExportJob;
use App\Models\DriveConnection;
use App\Models\DriveExport;
use App\Services\GoogleDrive\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DriveConnectionController extends Controller
{
    public function index()
    {
        return view('admin.drive.index', ['connections' => DriveConnection::withCount('exports')->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:80']]);
        DriveConnection::create(['label' => $data['label'], 'scopes' => ['drive.file'], 'active' => false]);

        return back()->with('status', 'Destino creado. Ahora conecta la cuenta Google.');
    }

    public function connect(Request $request, DriveConnection $connection, GoogleDriveService $drive)
    {
        $state = Str::random(64);
        $request->session()->put('drive_oauth', ['state' => $state, 'connection_id' => $connection->id]);

        return redirect()->away($drive->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDriveService $drive)
    {
        $oauth = $request->session()->pull('drive_oauth');
        abort_unless($oauth && hash_equals($oauth['state'], (string) $request->query('state')), 419);
        $request->validate(['code' => ['required', 'string']]);
        $connection = DriveConnection::findOrFail($oauth['connection_id']);
        $tokens = $drive->exchangeCode($request->query('code'));
        $connection->update([
            'google_email' => $drive->userEmail($tokens['access_token']),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600) - 60),
            'active' => true, 'last_error' => null,
        ]);
        $connection->update(['root_folder_id' => $connection->root_folder_id ?: $drive->createRoot($connection), 'last_verified_at' => now()]);

        return redirect()->route('admin.drive.index')->with('status', 'Google Drive conectado correctamente.');
    }

    public function verify(DriveConnection $connection, GoogleDriveService $drive)
    {
        $ok = $drive->verify($connection);

        return back()->with($ok ? 'status' : 'error', $ok ? 'Conexión verificada.' : 'No fue posible verificar la conexión.');
    }

    public function toggle(DriveConnection $connection)
    {
        $connection->update(['active' => ! $connection->active]);

        return back()->with('status', 'Estado del destino actualizado.');
    }

    public function retry(DriveExport $export)
    {
        $export->update(['status' => 'pending', 'last_error' => null]);
        PublishDriveExportJob::dispatch($export->id);

        return back()->with('status', 'Copia reenviada a la cola.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IndicatorStatus;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Indicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndicatorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-dashboards');

        $indicators = Indicator::query()
            ->with(['owner', 'approver'])
            ->when(
                ! $request->user()->canApproveDashboards(),
                fn ($query) => $query->where('owner_id', $request->user()->id)
            )
            ->orderBy('name')
            ->get();

        return view('admin.indicators.index', compact('indicators'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-dashboards');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash:ascii', 'unique:indicators,slug'],
            'summary' => ['nullable', 'string', 'max:700'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sector' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:80'],
            'periodicity' => ['nullable', 'string', 'max:80'],
            'technical_sheet' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $request->file('technical_sheet');
        $path = $file->store("indicators/{$data['slug']}/technical-sheets", 'local');

        Indicator::create([
            ...collect($data)->except('technical_sheet')->all(),
            'owner_id' => $request->user()->id,
            'status' => IndicatorStatus::Draft,
            'technical_sheet_disk' => 'local',
            'technical_sheet_path' => $path,
            'technical_sheet_original_name' => $file->getClientOriginalName(),
            'technical_sheet_mime' => $file->getMimeType() ?: 'application/pdf',
            'technical_sheet_size' => $file->getSize(),
        ]);

        return redirect(Workspace::getUrl(['workspace' => 'indicadores']))
            ->with('status', 'Indicador creado como borrador. Revise la ficha y envíelo a revisión cuando esté listo.');
    }

    public function update(Request $request, Indicator $indicator): RedirectResponse
    {
        abort_unless($indicator->canEdit($request->user()), 403);

        if ($indicator->isPublished()) {
            return back()->with('error', 'Este indicador ya está publicado. Para modificarlo se debe crear un nuevo borrador de versión.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash:ascii', Rule::unique('indicators', 'slug')->ignore($indicator->id)],
            'summary' => ['nullable', 'string', 'max:700'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sector' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:80'],
            'periodicity' => ['nullable', 'string', 'max:80'],
            'technical_sheet' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $attributes = collect($data)->except('technical_sheet')->all();

        if ($request->hasFile('technical_sheet')) {
            $file = $request->file('technical_sheet');
            if ($indicator->technical_sheet_path) {
                Storage::disk($indicator->technical_sheet_disk ?: 'local')->delete($indicator->technical_sheet_path);
            }
            $path = $file->store("indicators/{$data['slug']}/technical-sheets", 'local');
            $attributes += [
                'technical_sheet_disk' => 'local',
                'technical_sheet_path' => $path,
                'technical_sheet_original_name' => $file->getClientOriginalName(),
                'technical_sheet_mime' => $file->getMimeType() ?: 'application/pdf',
                'technical_sheet_size' => $file->getSize(),
            ];
        }

        $indicator->update([
            ...$attributes,
            'status' => IndicatorStatus::Draft,
            'submitted_at' => null,
        ]);

        return redirect(Workspace::getUrl(['workspace' => 'indicadores']))
            ->with('status', 'Indicador actualizado. La edición vuelve a quedar como borrador.');
    }

    public function submit(Request $request, Indicator $indicator): RedirectResponse
    {
        abort_unless($indicator->canEdit($request->user()), 403);

        if (! $indicator->hasTechnicalSheet()) {
            return back()->with('error', 'Cargue la ficha técnica oficial en PDF antes de enviar a revisión.');
        }

        $indicator->update([
            'status' => IndicatorStatus::PendingReview,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Indicador enviado a revisión.');
    }
}

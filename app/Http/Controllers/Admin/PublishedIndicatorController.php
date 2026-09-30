<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IndicatorStatus;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Indicator;
use App\Models\IndicatorVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublishedIndicatorController extends Controller
{
    public function __invoke(Request $request, Indicator $indicator): RedirectResponse
    {
        Gate::authorize('approve-dashboards');

        if ($indicator->status !== IndicatorStatus::PendingReview) {
            return back()->with('error', 'El indicador debe estar pendiente de revisión antes de publicarse.');
        }

        if (! $indicator->hasTechnicalSheet()) {
            return back()->with('error', 'No se puede publicar: falta la ficha técnica oficial en PDF.');
        }
        if (! $indicator->hasDataSeries()) {
            return back()->with('error', 'No se puede publicar: falta la serie de datos del indicador.');
        }

        $version = ((int) $indicator->versions()->max('version')) + 1;
        $publishedAt = now();

        IndicatorVersion::create([
            'indicator_id' => $indicator->id,
            'version' => $version,
            'payload' => [
                'name' => $indicator->name,
                'slug' => $indicator->slug,
                'summary' => $indicator->summary,
                'description' => $indicator->description,
                'sector' => $indicator->sector,
                'unit' => $indicator->unit,
                'periodicity' => $indicator->periodicity,
                'source_type' => $indicator->source_type,
                'tabular_data_source_id' => $indicator->tabular_data_source_id,
                'technical_sheet' => [
                    'disk' => $indicator->technical_sheet_disk,
                    'path' => $indicator->technical_sheet_path,
                    'original_name' => $indicator->technical_sheet_original_name,
                    'mime' => $indicator->technical_sheet_mime,
                    'size' => $indicator->technical_sheet_size,
                ],
            ],
            'created_by' => $indicator->owner_id,
            'approved_by' => $request->user()->id,
            'published_at' => $publishedAt,
        ]);

        $indicator->update([
            'status' => IndicatorStatus::Published,
            'published_version' => $version,
            'approved_by' => $request->user()->id,
            'published_at' => $publishedAt,
        ]);

        return redirect(Workspace::getUrl(['workspace' => 'indicadores']))
            ->with('status', 'Indicador publicado. La URL pública y el QR ya están disponibles.');
    }
}

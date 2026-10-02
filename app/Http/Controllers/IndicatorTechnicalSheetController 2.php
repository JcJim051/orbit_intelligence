<?php

namespace App\Http\Controllers;

use App\Enums\IndicatorStatus;
use App\Models\Indicator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndicatorTechnicalSheetController extends Controller
{
    public function __invoke(Indicator $indicator): BinaryFileResponse
    {
        abort_unless($indicator->status === IndicatorStatus::Published, 404);
        abort_unless($indicator->technical_sheet_path, 404);

        $disk = Storage::disk($indicator->technical_sheet_disk ?: 'local');
        abort_unless($disk->exists($indicator->technical_sheet_path), 404);

        return response()->file($disk->path($indicator->technical_sheet_path), [
            'Content-Type' => $indicator->technical_sheet_mime ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($indicator->technical_sheet_original_name ?: 'ficha-tecnica.pdf').'"',
        ]);
    }
}

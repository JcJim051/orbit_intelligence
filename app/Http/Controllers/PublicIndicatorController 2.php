<?php

namespace App\Http\Controllers;

use App\Enums\IndicatorStatus;
use App\Models\Indicator;
use chillerlan\QRCode\QRCode;
use Illuminate\View\View;

class PublicIndicatorController extends Controller
{
    public function __invoke(Indicator $indicator): View
    {
        abort_unless($indicator->status === IndicatorStatus::Published, 404);

        $otherIndicators = Indicator::query()
            ->where('status', IndicatorStatus::Published)
            ->whereKeyNot($indicator->getKey())
            ->orderBy('name')
            ->limit(12)
            ->get();

        $qrCode = (new QRCode)->render(route('indicators.show', $indicator));

        return view('indicators.show', compact('indicator', 'otherIndicators', 'qrCode'));
    }
}

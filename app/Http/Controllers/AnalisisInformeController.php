<?php

namespace App\Http\Controllers;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Services\InteligenciaGeografica\InformePdf;

class AnalisisInformeController extends Controller
{
    public function pdf(AnalisisArea $analisis, InformePdf $informe)
    {
        $this->authorize('view', $analisis);

        return response($informe->pdf($analisis), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="informe-analisis-'.$analisis->id.'.pdf"',
        ]);
    }
}

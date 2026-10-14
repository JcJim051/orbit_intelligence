<?php

namespace App\Http\Controllers\InteligenciaGeografica;

use App\Filament\Resources\ClasificacionAashtoResource;
use App\Filament\Resources\ClasificacionUscsResource;
use App\Filament\Resources\EstadoValidacionResource;
use App\Filament\Resources\MetodoCoordenadasResource;
use App\Filament\Resources\PerfilSueloNsr10Resource;
use App\Filament\Resources\SistemaCoordenadasResource;
use App\Filament\Resources\TipoAdjuntoResource;
use App\Filament\Resources\TipoCimentacionResource;
use App\Filament\Resources\TipoEnsayoResource;
use App\Filament\Resources\TipoEstudioResource;
use App\Filament\Resources\TipoExploracionResource;
use App\Filament\Resources\UnidadMedidaResource;
use App\Filament\Suelos\CatalogoSuelosResource;
use App\Http\Controllers\Controller;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\Suelos\EstudioSuelos;
use App\Services\InteligenciaGeografica\CapaExcel;
use App\Services\Suelos\CatalogoExcel;
use App\Services\Suelos\LibroEstudiosSuelos;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelController extends Controller
{
    public function exportarCapas(CapaExcel $excel): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('export', Capa::class), 403);

        return $this->archivo(function (string $ruta) use ($excel): void {
            $excel->exportar($ruta);
        }, 'capas.xlsx');
    }

    public function plantillaCapas(CapaExcel $excel): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('export', Capa::class), 403);

        return $this->archivo(function (string $ruta) use ($excel): void {
            $excel->plantilla($ruta);
        }, 'plantilla-capas.xlsx');
    }

    public function importarCapas(Request $request, CapaExcel $excel): mixed
    {
        abort_unless($request->user()?->can('import', Capa::class), 403);
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);
        $excel->importar($request->file('archivo')->getRealPath());

        return back();
    }

    public function exportarCatalogo(string $catalogo, CatalogoExcel $excel): BinaryFileResponse
    {
        $recurso = $this->recurso($catalogo);
        abort_unless(request()->user()?->can('export', $recurso::getModel()), 403);

        return $this->archivo(function (string $ruta) use ($excel, $recurso): void {
            $excel->exportar($recurso::getModel(), $recurso::definicionColumnas(), $ruta);
        }, $catalogo.'.xlsx');
    }

    public function plantillaCatalogo(string $catalogo, CatalogoExcel $excel): BinaryFileResponse
    {
        $recurso = $this->recurso($catalogo);
        abort_unless(request()->user()?->can('export', $recurso::getModel()), 403);

        return $this->archivo(function (string $ruta) use ($excel, $recurso): void {
            $excel->plantilla($recurso::definicionColumnas(), $ruta);
        }, 'plantilla-'.$catalogo.'.xlsx');
    }

    public function importarCatalogo(Request $request, string $catalogo, CatalogoExcel $excel): mixed
    {
        $recurso = $this->recurso($catalogo);
        abort_unless($request->user()?->can('import', $recurso::getModel()), 403);
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);
        $excel->importar($recurso::getModel(), $recurso::definicionColumnas(), $request->file('archivo')->getRealPath());

        return back();
    }

    public function plantillaEstudios(LibroEstudiosSuelos $libro): BinaryFileResponse
    {
        abort_unless(request()->user()?->can('export', EstudioSuelos::class), 403);

        return $this->archivo(function (string $ruta) use ($libro): void {
            $libro->plantilla($ruta);
        }, 'plantilla-estudios-suelos.xlsx');
    }

    public function importarEstudios(Request $request, LibroEstudiosSuelos $libro): mixed
    {
        abort_unless($request->user()?->can('import', EstudioSuelos::class), 403);
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);
        $libro->importar($request->file('archivo')->getRealPath(), $request->user());

        return back();
    }

    /**
     * @return class-string<CatalogoSuelosResource>
     */
    private function recurso(string $catalogo): string
    {
        foreach ([
            ClasificacionUscsResource::class,
            ClasificacionAashtoResource::class,
            TipoEnsayoResource::class,
            UnidadMedidaResource::class,
            TipoEstudioResource::class,
            TipoExploracionResource::class,
            TipoAdjuntoResource::class,
            TipoCimentacionResource::class,
            MetodoCoordenadasResource::class,
            PerfilSueloNsr10Resource::class,
            SistemaCoordenadasResource::class,
            EstadoValidacionResource::class,
        ] as $recurso) {
            if ($recurso::clave() === $catalogo) {
                return $recurso;
            }
        }

        abort(404);
    }

    private function archivo(callable $escribir, string $nombre): BinaryFileResponse
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $escribir($ruta);

        return response()->download($ruta, $nombre)->deleteFileAfterSend(true);
    }
}

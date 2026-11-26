<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del hero claro y de los contadores.
 * Si la base ya los tiene (PR anterior o su limpieza a medias), no los vuelve a crear.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->columna('micrositio_heroes', 'eyebrow', fn (Blueprint $table) => $table->string('eyebrow', 120)->nullable());
        $this->columna('micrositio_heroes', 'fuentes_linea', fn (Blueprint $table) => $table->string('fuentes_linea', 255)->nullable());
        $this->columna('micrositio_heroes', 'pildoras', fn (Blueprint $table) => $table->json('pildoras')->nullable());
        $this->columna('micrositio_heroes', 'usar_imagen_fondo', fn (Blueprint $table) => $table->boolean('usar_imagen_fondo')->default(false));

        $this->columna('micrositio_cifras', 'origen', fn (Blueprint $table) => $table->string('origen', 40)->default('manual'));
        $this->columna('micrositio_cifras', 'criterio', fn (Blueprint $table) => $table->string('criterio', 30)->default('activos'));
        $this->columna('micrositio_cifras', 'etiqueta', fn (Blueprint $table) => $table->string('etiqueta', 120)->nullable());

        $this->completarHero();
        $this->completarContadores();
    }

    public function down(): void
    {
        foreach (['eyebrow', 'fuentes_linea', 'pildoras', 'usar_imagen_fondo'] as $columna) {
            $this->quitar('micrositio_heroes', $columna);
        }

        foreach (['origen', 'criterio', 'etiqueta'] as $columna) {
            $this->quitar('micrositio_cifras', $columna);
        }
    }

    private function columna(string $tabla, string $columna, callable $definir): void
    {
        if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, $columna)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($definir): void {
            $definir($table);
        });
    }

    private function quitar(string $tabla, string $columna): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($columna): void {
            $table->dropColumn($columna);
        });
    }

    private function completarHero(): void
    {
        if (! Schema::hasColumn('micrositio_heroes', 'eyebrow')) {
            return;
        }

        $pildoras = json_encode([
            ['texto' => 'Indicadores', 'url' => '/micrositio/indicadores'],
            ['texto' => 'Políticas públicas', 'url' => '/micrositio/politicas-publicas'],
            ['texto' => 'Publicaciones', 'url' => '/micrositio#publicaciones'],
            ['texto' => 'Visor geográfico', 'url' => '/micrositio/geovisores'],
        ], JSON_UNESCAPED_UNICODE);

        DB::table('micrositio_heroes')
            ->whereNull('eyebrow')
            ->update([
                'eyebrow' => 'Sistema Integral de Información Departamental',
                'fuentes_linea' => 'Fuentes: Sistema Integral de Información Departamental (SIID) · SSPP · DANE · MinEducación. Corte: 13/11/2026.',
                'pildoras' => $pildoras,
                'usar_imagen_fondo' => false,
            ]);

        DB::table('micrositio_heroes')
            ->where('subtitulo', 'Datos abiertos, indicadores y estudios para decidir mejor en el Meta')
            ->update([
                'subtitulo' => 'Datos abiertos, indicadores y estudios que convierten la información del Meta en evidencia para planear, decidir y hacer seguimiento a la gestión pública.',
            ]);
    }

    private function completarContadores(): void
    {
        if (! Schema::hasColumn('micrositio_cifras', 'origen')) {
            return;
        }

        $origenes = ['indicadores_sectoriales', 'indicadores_territoriales', 'politicas_publicas'];

        if (DB::table('micrositio_cifras')->whereIn('origen', $origenes)->exists()) {
            return;
        }

        $ahora = now();
        $orden = (int) DB::table('micrositio_cifras')->max('orden');

        foreach ([
            ['203', 'indicadores_sectoriales', 'Indicadores Sectoriales', 'Indicadores sectoriales activos del listado maestro', 'SIID — indicadores activos'],
            ['154', 'indicadores_territoriales', 'Indicadores Territoriales (Propios)', 'Indicadores territoriales propios activos', 'Indicadores territoriales activos'],
            ['18', 'politicas_publicas', 'Políticas Públicas', 'Políticas públicas publicadas, sin ejemplos', 'Micrositio — políticas publicadas'],
        ] as [$valor, $origen, $etiqueta, $descripcion, $fuente]) {
            $orden++;
            DB::table('micrositio_cifras')->insert([
                'valor' => $valor,
                'origen' => $origen,
                'criterio' => 'activos',
                'etiqueta' => $etiqueta,
                'descripcion' => $descripcion,
                'fuente' => $fuente,
                'fecha_corte' => '2026-11-13',
                'es_ejemplo' => false,
                'orden' => $orden,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('micrositio_menus') || ! Schema::hasTable('micrositio_secciones')) {
            return;
        }

        DB::table('micrositio_menus')
            ->where('url', '/micrositio#publicaciones')
            ->update(['url' => '/micrositio/publicaciones', 'updated_at' => now()]);

        foreach ($this->entries() as $entry) {
            $existing = DB::table('micrositio_menus')->where('url', $entry['url'])->first();

            if ($existing) {
                DB::table('micrositio_menus')->where('id', $existing->id)->update([
                    'texto' => $entry['texto'],
                    'orden' => $entry['orden'],
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('micrositio_menus')->insert([
                ...$entry,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('micrositio_menus')->where('url', 'https://meta.gov.co')->update([
            'orden' => 90,
            'updated_at' => now(),
        ]);

        $this->publishCurrentMenu();
    }

    public function down(): void
    {
        if (! Schema::hasTable('micrositio_menus') || ! Schema::hasTable('micrositio_secciones')) {
            return;
        }

        DB::table('micrositio_menus')->whereIn('url', [
            '/micrositio/politicas-publicas',
            '/micrositio/geovisores',
            '/micrositio/analisis-geoespacial',
        ])->delete();

        $this->publishCurrentMenu();
    }

    /** @return list<array{texto: string, url: string, orden: int}> */
    private function entries(): array
    {
        return [
            ['texto' => 'Inicio', 'url' => '/micrositio', 'orden' => 0],
            ['texto' => 'Indicadores', 'url' => '/micrositio/indicadores', 'orden' => 1],
            ['texto' => 'Políticas Públicas', 'url' => '/micrositio/politicas-publicas', 'orden' => 2],
            ['texto' => 'Publicaciones', 'url' => '/micrositio/publicaciones', 'orden' => 3],
            ['texto' => 'Geovisores', 'url' => '/micrositio/geovisores', 'orden' => 4],
            ['texto' => 'Análisis geoespacial', 'url' => '/micrositio/analisis-geoespacial', 'orden' => 5],
        ];
    }

    private function publishCurrentMenu(): void
    {
        $records = DB::table('micrositio_menus')->orderBy('orden')->orderBy('id')->get()->map(fn (object $record): array => [
            'id' => (int) $record->id,
            'atributos' => [
                'texto' => (string) $record->texto,
                'url' => (string) $record->url,
                'orden' => (int) $record->orden,
            ],
        ])->all();

        DB::table('micrositio_secciones')->updateOrInsert(
            ['clave' => 'menu'],
            [
                'publicado' => json_encode(['registros' => $records], JSON_THROW_ON_ERROR),
                'publicado_en' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS siid_publicacion');
        }

        $prefix = Schema::getConnection()->getDriverName() === 'pgsql' ? 'siid_publicacion.' : '';

        if (! Schema::hasTable($prefix.'publicaciones_geovisores')) {
            Schema::create($prefix.'publicaciones_geovisores', function (Blueprint $table) {
                $table->id();
                $table->ulid('geo_viewer_id')->unique();
                $table->boolean('visible')->default(false)->index();
                $table->boolean('destacado')->default(false)->index();
                $table->unsignedInteger('orden')->default(0);
                $table->string('titulo_publico')->nullable();
                $table->text('descripcion_publica')->nullable();
                $table->string('imagen_path')->nullable();
                $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('publicado_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'herramientas_publicas')) {
            Schema::create($prefix.'herramientas_publicas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 80)->unique();
                $table->boolean('visible')->default(false)->index();
                $table->string('titulo');
                $table->text('descripcion')->nullable();
                $table->string('imagen_path')->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('publicado_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'publicaciones_geovisores_auditoria')) {
            Schema::create($prefix.'publicaciones_geovisores_auditoria', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publicacion_geovisor_id');
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('evento', 30);
                $table->json('antes')->nullable();
                $table->json('despues')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['publicacion_geovisor_id', 'created_at'], 'pub_geovisor_audit_record_date_idx');
            });
        }

        DB::table($prefix.'herramientas_publicas')->updateOrInsert(
            ['codigo' => 'analisis_geoespacial'],
            [
                'visible' => false,
                'titulo' => 'Análisis geoespacial',
                'descripcion' => 'Analice un área frente a las capas oficiales habilitadas por el equipo SIG.',
                'orden' => 20,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        if (Schema::hasTable('geo_viewers')) {
            DB::table('geo_viewers')->orderBy('id')->each(function (object $viewer) use ($prefix): void {
                $published = ($viewer->status ?? null) === 'published';
                DB::table($prefix.'publicaciones_geovisores')->updateOrInsert(
                    ['geo_viewer_id' => $viewer->id],
                    [
                        'visible' => $published,
                        'publicado_at' => $published ? ($viewer->published_at ?? now()) : null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = Schema::getConnection()->getDriverName() === 'pgsql' ? 'siid_publicacion.' : '';
        Schema::dropIfExists($prefix.'publicaciones_geovisores_auditoria');
        Schema::dropIfExists($prefix.'herramientas_publicas');
        Schema::dropIfExists($prefix.'publicaciones_geovisores');
    }
};

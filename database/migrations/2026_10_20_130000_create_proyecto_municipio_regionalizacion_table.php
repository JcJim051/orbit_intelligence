<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto_municipio')) {
            Schema::create('proyecto_municipio', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
                $table->foreignId('municipio_id')->constrained('municipios')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['proyecto_id', 'municipio_id']);
            });
        }

        DB::table('proyectos')
            ->whereNotNull('municipio_id')
            ->where('tipo_focalizacion', 'municipal')
            ->orderBy('id')
            ->each(function (object $proyecto): void {
                DB::table('proyecto_municipio')->updateOrInsert(
                    ['proyecto_id' => $proyecto->id, 'municipio_id' => $proyecto->municipio_id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_municipio');
    }
};

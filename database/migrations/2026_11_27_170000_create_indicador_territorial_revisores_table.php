<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_territorial_revisores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicador_territorial_id')->constrained('indicadores_territoriales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->unique(['indicador_territorial_id', 'user_id']);
            $table->index(['user_id', 'indicador_territorial_id']);
        });

        DB::table('indicadores_territoriales')
            ->whereNotNull('asignado_a')
            ->orderBy('id')
            ->select(['id', 'asignado_a'])
            ->chunkById(500, function ($indicadores): void {
                $ahora = now();
                $filas = $indicadores->map(fn ($indicador): array => [
                    'indicador_territorial_id' => $indicador->id,
                    'user_id' => $indicador->asignado_a,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all();

                DB::table('indicador_territorial_revisores')->insertOrIgnore($filas);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_territorial_revisores');
    }
};

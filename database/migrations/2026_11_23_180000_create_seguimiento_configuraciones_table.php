<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seguimiento_configuraciones')) {
            Schema::create('seguimiento_configuraciones', function (Blueprint $table): void {
                $table->id();
                $table->boolean('reporte_indicadores_activo')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        DB::table('seguimiento_configuraciones')->insertOrIgnore([
            [
                'id' => 1,
                'reporte_indicadores_activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_configuraciones');
    }
};

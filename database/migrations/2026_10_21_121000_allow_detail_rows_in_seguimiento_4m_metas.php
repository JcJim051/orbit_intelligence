<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_4m_metas', function (Blueprint $table): void {
            $table->unsignedInteger('fila_fuente')->nullable()->after('fuente');
        });

        Schema::table('seguimiento_4m_metas', function (Blueprint $table): void {
            $table->dropUnique(['codigo_meta_producto', 'vigencia']);
            $table->unique(['codigo_meta_producto', 'vigencia', 'fila_fuente']);
        });
    }

    public function down(): void
    {
        Schema::table('seguimiento_4m_metas', function (Blueprint $table): void {
            $table->dropUnique(['codigo_meta_producto', 'vigencia', 'fila_fuente']);
            $table->unique(['codigo_meta_producto', 'vigencia']);
            $table->dropColumn('fila_fuente');
        });
    }
};

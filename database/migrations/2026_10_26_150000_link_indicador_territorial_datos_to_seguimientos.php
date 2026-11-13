<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicador_territorial_datos', function (Blueprint $table): void {
            $table->foreignId('seguimiento_id')->nullable()->after('indicador_territorial_id')->constrained('seguimientos')->nullOnDelete();
            $table->timestamp('reportado_at')->nullable()->after('reportado_por');
            $table->text('observacion_revision')->nullable()->after('validado_at');
            $table->index(['seguimiento_id', 'estado'], 'indicador_datos_seguimiento_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('indicador_territorial_datos', function (Blueprint $table): void {
            $table->dropIndex('indicador_datos_seguimiento_estado_idx');
            $table->dropConstrainedForeignId('seguimiento_id');
            $table->dropColumn(['reportado_at', 'observacion_revision']);
        });
    }
};

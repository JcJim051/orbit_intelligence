<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metas_producto', function (Blueprint $table): void {
            $table->foreignId('tipologia_inversion_id')->nullable()->after('dependencia_id')->constrained('tipologias_inversion')->restrictOnDelete();
            $table->text('tipologia_observacion')->nullable()->after('tipologia_inversion_id');
            $table->string('tipologia_clasificada_por', 180)->nullable()->after('tipologia_observacion');
            $table->timestamp('tipologia_clasificada_at')->nullable()->after('tipologia_clasificada_por');
            $table->index(['tipologia_inversion_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::table('metas_producto', function (Blueprint $table): void {
            $table->dropForeign(['tipologia_inversion_id']);
            $table->dropIndex(['tipologia_inversion_id', 'activo']);
            $table->dropColumn([
                'tipologia_inversion_id',
                'tipologia_observacion',
                'tipologia_clasificada_por',
                'tipologia_clasificada_at',
            ]);
        });
    }
};

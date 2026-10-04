<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_indicativo_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_producto_id')->constrained('metas_producto')->cascadeOnDelete();
            $table->unsignedSmallInteger('vigencia');
            $table->decimal('valor_programado', 20, 4)->default(0);
            $table->text('observacion')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['meta_producto_id', 'vigencia'], 'plan_indicativo_meta_vigencia_unica');
            $table->index('vigencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_indicativo_metas');
    }
};

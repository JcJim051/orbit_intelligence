<?php

use App\Services\Analitica\EsquemaAnalitica;
use App\Services\Analitica\InstaladorVistasAnalitica;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(EsquemaAnalitica::tabla('pasiva_rubro'), function (Blueprint $table): void {
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('pasiva_carga_id')->nullable();
            $table->timestamp('cargado_en')->nullable();
            $table->string('origen')->nullable();
            $table->index(['corte', 'sha256']);
        });

        Schema::create('analitica_sincronizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pasiva_carga_id')->nullable()->constrained('pasiva_cargas')->nullOnDelete();
            $table->date('corte')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('estado', 20);
            $table->unsignedInteger('rubros')->default(0);
            $table->string('origen')->nullable();
            $table->text('mensaje')->nullable();
            $table->timestamps();
            $table->index(['corte', 'sha256']);
            $table->index('estado');
        });

        app(InstaladorVistasAnalitica::class)->instalar();
    }

    public function down(): void
    {
        $instalador = app(InstaladorVistasAnalitica::class);
        $instalador->eliminarVistas();
        Schema::dropIfExists(EsquemaAnalitica::tabla('diccionario'));
        Schema::dropIfExists('analitica_sincronizaciones');

        Schema::table(EsquemaAnalitica::tabla('pasiva_rubro'), function (Blueprint $table): void {
            $table->dropIndex(['corte', 'sha256']);
            $table->dropColumn(['sha256', 'pasiva_carga_id', 'cargado_en', 'origen']);
        });
    }
};

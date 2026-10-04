<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table): void {
            $table->string('modo_captura', 32)
                ->default('captura_operativa')
                ->after('estado');
        });

        Schema::table('actividades', function (Blueprint $table): void {
            $table->string('origen', 32)
                ->default('captura_sectorial')
                ->after('cantidad_programada');
        });
    }

    public function down(): void
    {
        Schema::table('actividades', function (Blueprint $table): void {
            $table->dropColumn('origen');
        });

        Schema::table('seguimientos', function (Blueprint $table): void {
            $table->dropColumn('modo_captura');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('micrositio_heroes', function (Blueprint $table): void {
            $table->string('eyebrow', 120)->nullable()->after('id');
            $table->string('fuentes_linea', 255)->nullable()->after('subtitulo');
            $table->json('pildoras')->nullable()->after('fuentes_linea');
            $table->boolean('usar_imagen_fondo')->default(false)->after('banner_path');
        });
    }

    public function down(): void
    {
        Schema::table('micrositio_heroes', function (Blueprint $table): void {
            $table->dropColumn(['eyebrow', 'fuentes_linea', 'pildoras', 'usar_imagen_fondo']);
        });
    }
};

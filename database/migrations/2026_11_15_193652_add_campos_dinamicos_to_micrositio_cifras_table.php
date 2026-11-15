<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('micrositio_cifras', function (Blueprint $table): void {
            $table->string('origen', 40)->default('manual')->after('valor');
            $table->string('criterio', 30)->default('activos')->after('origen');
            $table->string('etiqueta', 120)->nullable()->after('criterio');
        });
    }

    public function down(): void
    {
        Schema::table('micrositio_cifras', function (Blueprint $table): void {
            $table->dropColumn(['origen', 'criterio', 'etiqueta']);
        });
    }
};

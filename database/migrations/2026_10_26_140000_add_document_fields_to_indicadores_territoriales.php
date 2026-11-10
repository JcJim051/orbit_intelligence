<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->text('convencion')->nullable()->after('nombre');
            $table->text('limitaciones')->nullable()->after('observaciones_tecnicas');
        });
    }

    public function down(): void
    {
        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->dropColumn(['convencion', 'limitaciones']);
        });
    }
};

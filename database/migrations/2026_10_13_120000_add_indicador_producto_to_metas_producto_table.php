<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metas_producto', function (Blueprint $table): void {
            $table->text('indicador_producto')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('metas_producto', function (Blueprint $table): void {
            $table->dropColumn('indicador_producto');
        });
    }
};

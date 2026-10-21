<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimientos', function (Blueprint $table): void {
            $table->boolean('focalizacion_activa')->default(true);
            $table->boolean('distribucion_financiera_activa')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('seguimientos', function (Blueprint $table): void {
            $table->dropColumn(['focalizacion_activa', 'distribucion_financiera_activa']);
        });
    }
};

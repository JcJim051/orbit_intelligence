<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_cargas_historicas', function (Blueprint $table) {
            if (! Schema::hasColumn('seguimiento_cargas_historicas', 'proyectos_path')) {
                $table->string('proyectos_path')->nullable()->after('sha256');
            }

            if (! Schema::hasColumn('seguimiento_cargas_historicas', 'proyectos_nombre_original')) {
                $table->string('proyectos_nombre_original')->nullable()->after('proyectos_path');
            }

            if (! Schema::hasColumn('seguimiento_cargas_historicas', 'proyectos_sha256')) {
                $table->char('proyectos_sha256', 64)->nullable()->after('proyectos_nombre_original');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimiento_cargas_historicas', function (Blueprint $table) {
            if (Schema::hasColumn('seguimiento_cargas_historicas', 'proyectos_sha256')) {
                $table->dropColumn('proyectos_sha256');
            }

            if (Schema::hasColumn('seguimiento_cargas_historicas', 'proyectos_nombre_original')) {
                $table->dropColumn('proyectos_nombre_original');
            }

            if (Schema::hasColumn('seguimiento_cargas_historicas', 'proyectos_path')) {
                $table->dropColumn('proyectos_path');
            }
        });
    }
};

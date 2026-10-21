<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('proyectos', 'regionalizacion_modo')) {
            Schema::table('proyectos', function (Blueprint $table): void {
                $table->string('regionalizacion_modo', 24)->nullable()->after('tipo_focalizacion');
            });
        }

        if (! Schema::hasColumn('meta_producto_proyecto', 'regionaliza')) {
            Schema::table('meta_producto_proyecto', function (Blueprint $table): void {
                $table->boolean('regionaliza')->default(false)->after('proyecto_id');
                $table->index(['proyecto_id', 'regionaliza']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meta_producto_proyecto', 'regionaliza')) {
            Schema::table('meta_producto_proyecto', function (Blueprint $table): void {
                $table->dropIndex(['proyecto_id', 'regionaliza']);
                $table->dropColumn('regionaliza');
            });
        }

        if (Schema::hasColumn('proyectos', 'regionalizacion_modo')) {
            Schema::table('proyectos', function (Blueprint $table): void {
                $table->dropColumn('regionalizacion_modo');
            });
        }
    }
};

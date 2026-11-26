<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('micrositio_indicadores', function (Blueprint $table): void {
            $table->foreignUlid('dashboard_id')
                ->nullable()
                ->after('micrositio_tema_id')
                ->constrained('dashboards')
                ->nullOnDelete();
        });

        DB::table('micrositio_indicadores')
            ->whereNotNull('dashboard_uuid')
            ->get(['id', 'dashboard_uuid'])
            ->each(function (object $indicador): void {
                $dashboardId = DB::table('dashboards')
                    ->where('superset_embedded_uuid', $indicador->dashboard_uuid)
                    ->value('id');

                if ($dashboardId !== null) {
                    DB::table('micrositio_indicadores')
                        ->where('id', $indicador->id)
                        ->update(['dashboard_id' => $dashboardId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('micrositio_indicadores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dashboard_id');
        });
    }
};

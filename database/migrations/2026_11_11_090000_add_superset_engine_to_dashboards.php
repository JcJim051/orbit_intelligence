<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table): void {
            // Los registros existentes pertenecen al constructor nativo y se conservan
            // como legado. Los dashboards nuevos se crean explícitamente con Superset.
            $table->string('engine', 30)->default('siid_native')->index();
            $table->unsignedBigInteger('superset_dashboard_id')->nullable()->unique();
            $table->uuid('superset_embedded_uuid')->nullable()->unique();
            $table->unsignedBigInteger('superset_dataset_id')->nullable()->index();
            $table->jsonb('wizard_config')->nullable();
            $table->timestampTz('superset_synced_at')->nullable();
            $table->text('superset_sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table): void {
            $table->dropColumn([
                'engine',
                'superset_dashboard_id',
                'superset_embedded_uuid',
                'superset_dataset_id',
                'wizard_config',
                'superset_synced_at',
                'superset_sync_error',
            ]);
        });
    }
};

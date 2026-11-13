<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('micrositio_indicadores', function (Blueprint $table) {
            $table->foreignUlid('tabular_data_source_id')
                ->nullable()
                ->after('dashboard_id')
                ->constrained('tabular_data_sources')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('micrositio_indicadores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tabular_data_source_id');
        });
    }
};

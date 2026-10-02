<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table): void {
            $table->foreignUlid('tabular_data_source_id')
                ->nullable()
                ->after('source_type')
                ->constrained('tabular_data_sources')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tabular_data_source_id');
        });
    }
};

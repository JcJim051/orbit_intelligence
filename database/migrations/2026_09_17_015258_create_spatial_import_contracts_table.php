<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spatial_import_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('spatial_import_id')->constrained()->cascadeOnDelete();
            $table->string('source_table', 255);
            $table->foreignUlid('spatial_dataset_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('field_mapping');
            $table->string('status')->default('contract_draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->unique(['spatial_import_id', 'source_table']);
        });

        DB::table('spatial_imports')
            ->whereNotNull('spatial_dataset_id')
            ->whereNotNull('selected_table')
            ->orderBy('id')
            ->chunkById(100, function ($imports): void {
                DB::table('spatial_import_contracts')->insert($imports->map(fn ($import): array => [
                    'id' => (string) Str::ulid(),
                    'spatial_import_id' => $import->id,
                    'source_table' => $import->selected_table,
                    'spatial_dataset_id' => $import->spatial_dataset_id,
                    'field_mapping' => $import->field_mapping ?? '{}',
                    'status' => $import->status === 'approved' ? 'approved' : 'contract_draft',
                    'approved_by' => $import->approved_by,
                    'approved_at' => $import->approved_at,
                    'created_at' => $import->created_at,
                    'updated_at' => $import->updated_at,
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('spatial_import_contracts')->exists()) {
            throw new RuntimeException('Esta migración contiene asociaciones de capas. No se puede revertir sin perderlas.');
        }

        Schema::dropIfExists('spatial_import_contracts');
    }
};

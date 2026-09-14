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
        Schema::create('investment_progress_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->decimal('physical_progress', 8, 4)->nullable();
            $table->decimal('financial_progress', 8, 4)->nullable();
            $table->decimal('current_value', 24, 2)->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
            $table->index(['investment_project_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_progress_reports');
    }
};

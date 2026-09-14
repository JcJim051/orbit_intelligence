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
        Schema::create('dataset_form_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dataset_form_version_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('section')->default('Información general');
            $table->text('help_text')->nullable();
            $table->string('field_type');
            $table->string('unit')->nullable();
            $table->boolean('required')->default(false);
            $table->jsonb('options')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->string('historical_policy')->default('future_only');
            $table->boolean('visible_in_qgis')->default(true);
            $table->boolean('public_visible')->default(false);
            $table->boolean('available_for_analytics')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['dataset_form_version_id', 'key']);
            $table->index(['dataset_form_version_id', 'section', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_form_fields');
    }
};

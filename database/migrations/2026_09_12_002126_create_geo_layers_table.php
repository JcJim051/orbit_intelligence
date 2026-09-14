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
        Schema::create('geo_layers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group_name')->nullable();
            $table->string('source_type')->default('geojson');
            $table->text('source_url');
            $table->string('source_layer_name')->nullable();
            $table->string('geometry_type')->default('mixed');
            $table->jsonb('popup_fields')->nullable();
            $table->jsonb('style')->nullable();
            $table->string('attribution', 500)->nullable();
            $table->unsignedTinyInteger('min_zoom')->default(0);
            $table->unsignedTinyInteger('max_zoom')->default(18);
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('geo_viewer_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('geo_viewer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('geo_layer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('group_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('visible_by_default')->default(false);
            $table->boolean('show_in_legend')->default(true);
            $table->decimal('opacity', 3, 2)->default(1);
            $table->timestampsTz();
            $table->unique(['geo_viewer_id', 'geo_layer_id']);
            $table->index(['geo_viewer_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_viewer_layers');
        Schema::dropIfExists('geo_layers');
    }
};

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
        Schema::create('geo_viewers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('center_latitude', 10, 7)->default(4.1500);
            $table->decimal('center_longitude', 10, 7)->default(-73.6300);
            $table->unsignedTinyInteger('initial_zoom')->default(8);
            $table->string('status')->default('draft')->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_viewers');
    }
};

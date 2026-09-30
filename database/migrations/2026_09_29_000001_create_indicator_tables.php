<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('sector')->nullable()->index();
            $table->string('unit')->nullable();
            $table->string('periodicity')->nullable();
            $table->string('source_type')->nullable()->index();
            $table->jsonb('public_metadata')->nullable();
            $table->string('technical_sheet_disk')->default('local');
            $table->string('technical_sheet_path')->nullable();
            $table->string('technical_sheet_original_name')->nullable();
            $table->string('technical_sheet_mime')->nullable();
            $table->unsignedBigInteger('technical_sheet_size')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('indicator_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('indicator_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at');
            $table->timestampsTz();
            $table->unique(['indicator_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_versions');
        Schema::dropIfExists('indicators');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabular_data_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('current_version')->default(0);
            $table->timestampsTz();
        });

        Schema::create('tabular_data_source_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tabular_data_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_filename');
            $table->string('checksum', 64);
            $table->unsignedInteger('row_count')->default(0);
            $table->jsonb('fields');
            $table->jsonb('records');
            $table->jsonb('validation_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['tabular_data_source_id', 'version'], 'tabular_source_version_unique');
        });

        Schema::create('dashboards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('draft_config')->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('dashboard_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('dashboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission')->default('edit');
            $table->timestampsTz();
            $table->unique(['dashboard_id', 'user_id']);
        });

        Schema::create('dashboard_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dashboard_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('config');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at');
            $table->timestampsTz();
            $table->unique(['dashboard_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_versions');
        Schema::dropIfExists('dashboard_collaborators');
        Schema::dropIfExists('dashboards');
        Schema::dropIfExists('tabular_data_source_versions');
        Schema::dropIfExists('tabular_data_sources');
    }
};

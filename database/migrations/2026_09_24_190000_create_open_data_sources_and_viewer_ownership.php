<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_viewers', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::create('geo_viewer_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('geo_viewer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission')->default('edit');
            $table->timestampsTz();
            $table->unique(['geo_viewer_id', 'user_id']);
        });

        Schema::create('open_data_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('geo_layer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider')->default('socrata');
            $table->string('dataset_id', 9)->index();
            $table->text('landing_page_url');
            $table->jsonb('metadata')->nullable();
            $table->string('schema_signature', 64);
            $table->string('geography_mode');
            $table->string('geometry_field')->nullable();
            $table->string('latitude_field')->nullable();
            $table->string('longitude_field')->nullable();
            $table->string('dane_field')->nullable();
            $table->string('label_field')->nullable();
            $table->string('metric_field')->nullable();
            $table->string('aggregation', 20)->default('count');
            $table->jsonb('filters')->nullable();
            $table->jsonb('popup_fields')->nullable();
            $table->jsonb('style')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('open_data_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('open_data_source_id')->constrained()->cascadeOnDelete();
            $table->string('query_hash', 64);
            $table->jsonb('filters')->nullable();
            $table->jsonb('payload');
            $table->unsignedInteger('feature_count')->default(0);
            $table->string('checksum', 64);
            $table->timestampTz('refreshed_at');
            $table->timestampsTz();
            $table->unique(['open_data_source_id', 'query_hash']);
            $table->index(['open_data_source_id', 'refreshed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_data_snapshots');
        Schema::dropIfExists('open_data_sources');
        Schema::dropIfExists('geo_viewer_collaborators');
        Schema::table('geo_viewers', fn (Blueprint $table) => $table->dropConstrainedForeignId('owner_id'));
    }
};

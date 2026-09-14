<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('investment_entities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('acronym')->nullable();
            $table->jsonb('aliases');
            $table->text('source_url');
            $table->boolean('active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('investment_entity_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('investment_entity_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('primary')->index();
            $table->string('status')->default('suggested')->index();
            $table->string('method');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->jsonb('evidence')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['investment_project_id', 'investment_entity_id'], 'investment_entity_assignment_unique');
        });

        Schema::table('investment_projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('horizon_start_year')->nullable()->index();
            $table->unsignedSmallInteger('horizon_end_year')->nullable()->index();
        });

        Schema::table('investment_sync_runs', function (Blueprint $table) {
            $table->unsignedInteger('classification_confirmed')->default(0);
            $table->unsignedInteger('classification_suggested')->default(0);
            $table->unsignedInteger('classification_unclassified')->default(0);
        });

        DB::table('investment_projects')->orderBy('id')->chunkById(100, function ($projects): void {
            foreach ($projects as $project) {
                preg_match_all('/(?:19|20|21)\d{2}/', (string) $project->horizon, $matches);
                $years = collect($matches[0] ?? [])->map(fn (string $year): int => (int) $year);
                DB::table('investment_projects')->where('id', $project->id)->update([
                    'horizon_start_year' => $years->min(),
                    'horizon_end_year' => $years->max(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_sync_runs', function (Blueprint $table) {
            $table->dropColumn(['classification_confirmed', 'classification_suggested', 'classification_unclassified']);
        });
        Schema::table('investment_projects', function (Blueprint $table) {
            $table->dropColumn(['horizon_start_year', 'horizon_end_year']);
        });
        Schema::dropIfExists('investment_entity_assignments');
        Schema::dropIfExists('investment_entities');
    }
};

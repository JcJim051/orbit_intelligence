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
        Schema::create('investment_sync_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('universe')->index();
            $table->string('status')->default('pending')->index();
            $table->jsonb('datasets');
            $table->unsignedInteger('project_limit')->nullable();
            $table->unsignedBigInteger('rows_received')->default(0);
            $table->unsignedBigInteger('rows_written')->default(0);
            $table->unsignedBigInteger('projects_touched')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('investment_source_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_sync_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dataset_id')->index();
            $table->string('dataset_name');
            $table->text('source_url');
            $table->string('status')->default('pending')->index();
            $table->timestampTz('queried_at');
            $table->timestampTz('cutoff_at')->nullable();
            $table->unsignedBigInteger('rows_received')->default(0);
            $table->unsignedBigInteger('rows_written')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->text('warning')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('investment_projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('bpin')->unique();
            $table->text('name')->nullable();
            $table->longText('objective')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('substatus')->nullable();
            $table->string('horizon')->nullable();
            $table->string('sector')->nullable()->index();
            $table->text('responsible_entity')->nullable();
            $table->string('responsible_entity_code')->nullable()->index();
            $table->text('executing_entity')->nullable();
            $table->string('project_type')->nullable()->index();
            $table->text('budget_program')->nullable();
            $table->text('national_development_plan')->nullable();
            $table->decimal('total_value', 24, 2)->nullable();
            $table->decimal('current_value', 24, 2)->nullable();
            $table->decimal('obligated_value', 24, 2)->nullable();
            $table->decimal('paid_value', 24, 2)->nullable();
            $table->unsignedBigInteger('beneficiaries_total')->nullable();
            $table->decimal('physical_progress', 8, 4)->nullable();
            $table->decimal('financial_progress', 8, 4)->nullable();
            $table->boolean('is_governor_meta')->default(false)->index();
            $table->boolean('is_territory_meta')->default(false)->index();
            $table->boolean('is_ecosystem_meta')->default(false)->index();
            $table->string('source_dataset_id')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable()->index();
            $table->jsonb('raw_data')->nullable();
            $table->timestampsTz();
        });

        Schema::create('investment_financials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
            $table->text('funding_source')->nullable();
            $table->text('funding_resource_type')->nullable();
            $table->text('funding_entity')->nullable();
            $table->decimal('requested_value', 24, 2)->nullable();
            $table->decimal('initial_value', 24, 2)->nullable();
            $table->decimal('current_value', 24, 2)->nullable();
            $table->decimal('committed_value', 24, 2)->nullable();
            $table->decimal('obligated_value', 24, 2)->nullable();
            $table->decimal('paid_value', 24, 2)->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
            $table->index(['investment_project_id', 'fiscal_year']);
        });

        Schema::create('investment_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->string('region_code')->nullable();
            $table->string('region')->nullable();
            $table->string('department_code')->nullable()->index();
            $table->string('department')->nullable();
            $table->string('municipality_code')->nullable()->index();
            $table->string('municipality')->nullable()->index();
            $table->boolean('is_department_wide')->default(false);
            $table->jsonb('raw_data');
            $table->timestampsTz();
            $table->index(['investment_project_id', 'municipality_code']);
        });

        Schema::create('investment_beneficiaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->string('department')->nullable();
            $table->string('municipality')->nullable()->index();
            $table->unsignedBigInteger('beneficiaries')->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
        });

        Schema::create('investment_products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->text('product')->nullable();
            $table->text('indicator')->nullable();
            $table->string('product_unit')->nullable();
            $table->string('indicator_unit')->nullable();
            $table->decimal('quantity', 24, 4)->nullable();
            $table->decimal('indicator_target', 24, 4)->nullable();
            $table->decimal('indicator_progress', 24, 4)->nullable();
            $table->decimal('product_value', 24, 2)->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
        });

        Schema::create('investment_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->string('reference')->nullable()->index();
            $table->text('supplier')->nullable();
            $table->string('supplier_document')->nullable();
            $table->string('status')->nullable()->index();
            $table->decimal('value', 24, 2)->nullable();
            $table->text('object')->nullable();
            $table->text('process_url')->nullable();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
        });

        Schema::create('investment_policy_focuses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->text('policy')->nullable();
            $table->text('dimension')->nullable();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->decimal('current_value', 24, 2)->nullable();
            $table->decimal('committed_value', 24, 2)->nullable();
            $table->decimal('obligated_value', 24, 2)->nullable();
            $table->decimal('paid_value', 24, 2)->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
        });

        Schema::create('investment_territorial_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('source_dataset_id');
            $table->string('source_row_hash', 64)->unique();
            $table->string('department')->nullable()->index();
            $table->string('municipality')->nullable()->index();
            $table->unsignedSmallInteger('fiscal_year')->nullable()->index();
            $table->text('entity')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('sector')->nullable();
            $table->text('funding_source')->nullable();
            $table->decimal('committed_value', 24, 2)->nullable();
            $table->decimal('obligated_value', 24, 2)->nullable();
            $table->decimal('paid_value', 24, 2)->nullable();
            $table->jsonb('raw_data');
            $table->timestampsTz();
        });

        Schema::create('investment_project_meeting', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('agenda_reason');
            $table->jsonb('prepared_questions')->nullable();
            $table->timestampsTz();
            $table->unique(['investment_project_id', 'meeting_id']);
        });

        Schema::create('project_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('investment_project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('meeting_summary_id')->constrained()->cascadeOnDelete();
            $table->text('decision');
            $table->timestampsTz();
        });

        Schema::table('action_items', function (Blueprint $table) {
            $table->foreignUlid('investment_project_id')->nullable()->after('meeting_summary_id')->constrained()->nullOnDelete();
            $table->index(['investment_project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_items', function (Blueprint $table) {
            $table->dropForeign(['investment_project_id']);
            $table->dropColumn('investment_project_id');
        });
        Schema::dropIfExists('project_decisions');
        Schema::dropIfExists('investment_project_meeting');
        Schema::dropIfExists('investment_territorial_resources');
        Schema::dropIfExists('investment_policy_focuses');
        Schema::dropIfExists('investment_contracts');
        Schema::dropIfExists('investment_products');
        Schema::dropIfExists('investment_beneficiaries');
        Schema::dropIfExists('investment_locations');
        Schema::dropIfExists('investment_financials');
        Schema::dropIfExists('investment_projects');
        Schema::dropIfExists('investment_source_snapshots');
        Schema::dropIfExists('investment_sync_runs');
    }
};

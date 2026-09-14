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
        Schema::table('spatial_datasets', function (Blueprint $table) {
            $table->string('physical_table')->nullable()->after('status');
            $table->unsignedInteger('materialized_form_version')->nullable()->after('physical_table');
            $table->timestampTz('materialized_at')->nullable()->after('materialized_form_version');
            $table->text('materialization_error')->nullable()->after('materialized_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spatial_datasets', function (Blueprint $table) {
            $table->dropColumn([
                'physical_table',
                'materialized_form_version',
                'materialized_at',
                'materialization_error',
            ]);
        });
    }
};

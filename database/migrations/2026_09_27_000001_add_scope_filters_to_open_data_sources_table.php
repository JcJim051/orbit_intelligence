<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('open_data_sources', function (Blueprint $table): void {
            $table->jsonb('scope_filters')->nullable()->after('aggregation');
        });
    }

    public function down(): void
    {
        Schema::table('open_data_sources', function (Blueprint $table): void {
            $table->dropColumn('scope_filters');
        });
    }
};

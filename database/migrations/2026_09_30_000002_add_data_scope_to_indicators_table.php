<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table): void {
            $table->jsonb('data_scope_config')->nullable()->after('public_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table): void {
            $table->dropColumn('data_scope_config');
        });
    }
};

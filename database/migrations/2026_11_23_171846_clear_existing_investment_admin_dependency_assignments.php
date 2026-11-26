<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dependencia_user') || ! Schema::hasTable('users')) {
            return;
        }

        DB::table('dependencia_user')
            ->whereIn('user_id', DB::table('users')->select('id')->where('role', 'investment_reviewer'))
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Las asignaciones eliminadas requieren una decisión administrativa y no se reconstruyen automáticamente.
    }
};

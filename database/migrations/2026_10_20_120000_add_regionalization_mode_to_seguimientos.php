<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seguimientos', 'modo_regionalizacion')) {
            Schema::table('seguimientos', function (Blueprint $table): void {
                $table->string('modo_regionalizacion', 32)
                    ->default('proyecto')
                    ->after('modo_captura');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seguimientos', 'modo_regionalizacion')) {
            Schema::table('seguimientos', function (Blueprint $table): void {
                $table->dropColumn('modo_regionalizacion');
            });
        }
    }
};

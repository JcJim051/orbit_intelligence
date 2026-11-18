<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->string('ficha_tecnica_pdf_path')->nullable()->after('url_ficha');
        });
    }

    public function down(): void
    {
        Schema::table('indicadores_territoriales', function (Blueprint $table): void {
            $table->dropColumn('ficha_tecnica_pdf_path');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('micrositio_politicas', function (Blueprint $table) {
            $table->json('documentos')->nullable();
            $table->json('nombres_documentos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('micrositio_politicas', function (Blueprint $table) {
            $table->dropColumn(['documentos', 'nombres_documentos']);
        });
    }
};

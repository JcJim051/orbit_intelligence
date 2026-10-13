<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meta_resultado_construccion_productos');
    }

    public function down(): void
    {
        // La captura de indicadores producto por meta producto fue retirada del módulo.
    }
};

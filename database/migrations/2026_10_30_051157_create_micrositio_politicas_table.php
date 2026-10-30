<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositio_politicas', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre', 200);
            $table->text('descripcion');
            $table->text('objetivo')->nullable();
            $table->string('ordenanza')->nullable();
            $table->string('vigencia')->nullable();
            $table->string('responsable')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('es_ejemplo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_politicas');
    }
};

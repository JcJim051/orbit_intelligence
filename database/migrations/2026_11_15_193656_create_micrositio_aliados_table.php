<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('micrositio_aliados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 160);
            $table->string('logo_path')->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('micrositio_aliados');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('secretariat', 150);
            $table->string('official_name', 150);
            $table->string('email', 254);
            $table->string('status')->default('received');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_mime_type')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_requests');
    }
};

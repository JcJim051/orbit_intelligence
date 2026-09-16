<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spatial_datasets', function (Blueprint $table) {
            $table->unsignedInteger('storage_srid')->default(4326);
        });
    }

    public function down(): void
    {
        Schema::table('spatial_datasets', function (Blueprint $table) {
            $table->dropColumn('storage_srid');
        });
    }
};

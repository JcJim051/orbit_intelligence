<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dataset_form_fields', function (Blueprint $table) {
            $table->unsignedInteger('introduced_in_version')->default(1)->after('historical_policy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_form_fields', function (Blueprint $table) {
            $table->dropColumn('introduced_in_version');
        });
    }
};

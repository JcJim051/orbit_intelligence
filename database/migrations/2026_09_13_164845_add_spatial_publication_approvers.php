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
        Schema::table('dataset_form_versions', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('geo_viewers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_form_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });
        Schema::table('geo_viewers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};

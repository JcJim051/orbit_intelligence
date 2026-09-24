<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_layers', function (Blueprint $table) {
            $table->boolean('is_open_data')->default(false)->after('attribution');
            $table->text('source_page_url')->nullable()->after('is_open_data');
        });
    }

    public function down(): void
    {
        Schema::table('geo_layers', function (Blueprint $table) {
            $table->dropColumn(['is_open_data', 'source_page_url']);
        });
    }
};

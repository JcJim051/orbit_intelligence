<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ods_indicators', function (Blueprint $table) {
            $table->text('source')->nullable()->after('description');
            $table->text('csv_url')->nullable()->after('source');
            $table->text('excel_url')->nullable()->after('csv_url');
            $table->timestamp('imported_at')->nullable()->after('excel_url');
        });
    }

    public function down(): void
    {
        Schema::table('ods_indicators', function (Blueprint $table) {
            $table->dropColumn(['source', 'csv_url', 'excel_url', 'imported_at']);
        });
    }
};

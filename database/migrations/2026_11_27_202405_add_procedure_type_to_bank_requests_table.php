<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_requests', function (Blueprint $table) {
            $table->string('procedure_type', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bank_requests', function (Blueprint $table) {
            $table->dropColumn('procedure_type');
        });
    }
};

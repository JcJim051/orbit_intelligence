<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_resultado_construcciones', function (Blueprint $table) {
            $table->index(['assigned_to', 'status'], 'meta_resultado_construcciones_assigned_status_idx');
            $table->index(['status', 'updated_at'], 'meta_resultado_construcciones_status_updated_idx');
        });

        Schema::table('meta_resultado_construccion_comentarios', function (Blueprint $table) {
            $table->index(['construccion_id', 'created_at'], 'meta_resultado_construccion_comments_task_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('meta_resultado_construccion_comentarios', function (Blueprint $table) {
            $table->dropIndex('meta_resultado_construccion_comments_task_date_idx');
        });

        Schema::table('meta_resultado_construcciones', function (Blueprint $table) {
            $table->dropIndex('meta_resultado_construcciones_status_updated_idx');
            $table->dropIndex('meta_resultado_construcciones_assigned_status_idx');
        });
    }
};

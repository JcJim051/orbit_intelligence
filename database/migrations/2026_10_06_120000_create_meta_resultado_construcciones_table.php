<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_resultado_construcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_resultado_id')->unique()->constrained('metas_resultado')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('measurement_mode', 32)->default('dual');
            $table->decimal('baseline_value', 20, 4)->nullable();
            $table->decimal('current_value', 20, 4)->nullable();
            $table->date('current_value_date')->nullable();
            $table->text('current_value_source')->nullable();
            $table->text('methodology_notes')->nullable();
            $table->decimal('management_progress_pct', 8, 4)->nullable();
            $table->decimal('result_progress_pct', 8, 4)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_resultado_construccion_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construccion_id')->constrained('meta_resultado_construcciones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32)->default('comment');
            $table->text('comment');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('meta_resultado_construccion_comentarios');
        Schema::dropIfExists('meta_resultado_construcciones');
    }
};

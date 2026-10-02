<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ods_goals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('ods_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ods_goal_id')->constrained('ods_goals')->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->text('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('ods_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ods_target_id')->constrained('ods_targets')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->text('name');
            $table->string('unit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('indicador_resultado_ods_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicador_resultado_id')->unique()->constrained('indicadores_resultado')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('indicador_resultado_ods_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('indicador_resultado_ods_reviews')->cascadeOnDelete();
            $table->foreignId('indicador_resultado_id')->constrained('indicadores_resultado')->cascadeOnDelete();
            $table->foreignId('ods_indicator_id')->constrained('ods_indicators')->restrictOnDelete();
            $table->string('relation_type', 32);
            $table->string('confidence', 16);
            $table->string('status', 32)->default('proposed');
            $table->text('justification')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['indicador_resultado_id', 'ods_indicator_id'], 'resultado_ods_unique');
        });

        Schema::create('indicador_resultado_ods_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('indicador_resultado_ods_reviews')->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained('indicador_resultado_ods_links')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32)->default('comment');
            $table->text('comment');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_resultado_ods_comments');
        Schema::dropIfExists('indicador_resultado_ods_links');
        Schema::dropIfExists('indicador_resultado_ods_reviews');
        Schema::dropIfExists('ods_indicators');
        Schema::dropIfExists('ods_targets');
        Schema::dropIfExists('ods_goals');
    }
};

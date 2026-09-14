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
        Schema::create('spatial_imports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->string('sector', 120);
            $table->text('purpose')->nullable();
            $table->string('status')->default('pending');
            $table->string('staging_schema', 63)->unique();
            $table->string('database_username', 63)->unique();
            $table->text('database_password');
            $table->timestampTz('expires_at');
            $table->jsonb('profile')->nullable();
            $table->timestampTz('profiled_at')->nullable();
            $table->string('selected_table')->nullable();
            $table->jsonb('field_mapping')->nullable();
            $table->foreignUlid('spatial_dataset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['sector', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spatial_imports');
    }
};

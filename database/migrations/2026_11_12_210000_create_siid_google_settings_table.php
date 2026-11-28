<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('siid_publicacion.google_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('spreadsheet_id', 180)->nullable();
            $table->jsonb('main_tabs')->nullable();
            $table->jsonb('complementary_tabs')->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->string('credentials_filename')->nullable();
            $table->string('service_account_email')->nullable();
            $table->string('last_status', 24)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::dropIfExists('siid_publicacion.google_settings');
        }
    }
};

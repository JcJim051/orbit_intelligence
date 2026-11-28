<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependencias', function (Blueprint $table) {
            $table->string('codigo_radicacion', 20)->nullable()->unique();
        });

        Schema::table('bank_requests', function (Blueprint $table) {
            $table->string('numero_seguimiento', 20)->nullable()->unique();
            $table->foreignId('dependencia_id')->nullable()->constrained('dependencias')->nullOnDelete();
            $table->index('email');
            $table->index('status');
        });

        DB::table('bank_requests')->where('status', 'received')->update(['status' => 'recibido']);
        DB::table('bank_requests')->where('status', 'in_review')->update(['status' => 'en_proceso']);
        DB::table('bank_requests')->where('status', 'completed')->update(['status' => 'aceptado']);

        DB::table('bank_requests')->whereNull('numero_seguimiento')->orderBy('id')->lazyById()->each(function (object $row): void {
            DB::table('bank_requests')->where('id', $row->id)->update([
                'numero_seguimiento' => 'BP-LEG-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::create('bank_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 20);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_mime_type')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->index(['bank_request_id', 'version']);
        });

        Schema::create('bank_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index('bank_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_request_events');
        Schema::dropIfExists('bank_request_files');

        Schema::table('bank_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dependencia_id');
            $table->dropIndex(['email']);
            $table->dropIndex(['status']);
            $table->dropUnique(['numero_seguimiento']);
            $table->dropColumn('numero_seguimiento');
        });

        Schema::table('dependencias', function (Blueprint $table) {
            $table->dropUnique(['codigo_radicacion']);
            $table->dropColumn('codigo_radicacion');
        });
    }
};

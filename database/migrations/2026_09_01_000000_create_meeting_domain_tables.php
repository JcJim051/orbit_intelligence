<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index();
            $table->boolean('active')->default(true);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('drive_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('label');
            $table->string('google_email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->string('root_folder_id')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->boolean('active')->default(false)->index();
            $table->timestampTz('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->timestampTz('held_at');
            $table->string('meeting_type');
            $table->jsonb('participants')->nullable();
            $table->boolean('recording_consent_confirmed');
            $table->timestampTz('consent_confirmed_at');
            $table->jsonb('artifacts');
            $table->jsonb('drive_connection_ids')->nullable();
            $table->string('idempotency_key');
            $table->string('status')->default('received')->index();
            $table->string('drive_sync_status')->default('not_requested')->index();
            $table->string('error_stage')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('meeting_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedBigInteger('offset_ms')->default(0);
            $table->string('disk');
            $table->text('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('md5', 32);
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestampTz('uploaded_at');
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['meeting_id', 'kind', 'sequence']);
        });

        Schema::create('transcripts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('language')->nullable();
            $table->longText('original_text');
            $table->longText('edited_text');
            $table->jsonb('speaker_map')->nullable();
            $table->jsonb('provider_metadata')->nullable();
            $table->timestampsTz();
            $table->unique('meeting_id');
        });

        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('transcript_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('meeting_file_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->string('speaker_key')->nullable();
            $table->text('original_text');
            $table->text('edited_text');
            $table->timestampsTz();
            $table->unique(['transcript_id', 'sequence']);
        });

        Schema::create('meeting_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('draft');
            $table->longText('executive_summary');
            $table->jsonb('topics');
            $table->jsonb('decisions');
            $table->jsonb('risks');
            $table->jsonb('pending_questions');
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_version');
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['meeting_id', 'version']);
        });

        Schema::create('action_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_summary_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('owner_name')->nullable();
            $table->date('due_date')->nullable();
            $table->string('due_date_text')->nullable();
            $table->text('source_excerpt')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestampsTz();
        });

        Schema::create('drive_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('meeting_summary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('drive_connection_id')->constrained()->cascadeOnDelete();
            $table->string('artifact_type');
            $table->string('status')->default('pending')->index();
            $table->text('remote_path')->nullable();
            $table->string('remote_file_id')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('md5', 32)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();
            $table->unique(['meeting_id', 'meeting_summary_id', 'drive_connection_id', 'artifact_type'], 'drive_export_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->string('stage')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('drive_exports');
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('meeting_summaries');
        Schema::dropIfExists('transcript_segments');
        Schema::dropIfExists('transcripts');
        Schema::dropIfExists('meeting_files');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('drive_connections');
        Schema::dropIfExists('personal_access_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'active']);
        });
    }
};

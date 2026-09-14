<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_layers', function (Blueprint $table) {
            $table->string('access_policy')->default('pending')->after('active')->index();
            $table->text('download_url')->nullable()->after('access_policy');
            $table->string('download_format', 20)->nullable()->after('download_url');
            $table->text('restriction_reason')->nullable()->after('download_format');
            $table->foreignId('access_policy_approved_by')->nullable()->after('restriction_reason')->constrained('users')->nullOnDelete();
            $table->timestampTz('access_policy_approved_at')->nullable()->after('access_policy_approved_by');
        });

        DB::table('geo_layers')->update([
            'access_policy' => 'downloadable',
            'download_format' => 'geojson',
        ]);
    }

    public function down(): void
    {
        Schema::table('geo_layers', function (Blueprint $table) {
            $table->dropForeign(['access_policy_approved_by']);
            $table->dropIndex(['access_policy']);
            $table->dropColumn([
                'access_policy', 'download_url', 'download_format', 'restriction_reason',
                'access_policy_approved_by', 'access_policy_approved_at',
            ]);
        });
    }
};

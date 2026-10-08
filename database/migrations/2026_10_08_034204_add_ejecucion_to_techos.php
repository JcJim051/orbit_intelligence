<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega comprometido, obligado y pagado al techo, con el mismo esquema
     * pasiva / ajuste / valor que ya tiene el asignado. Las filas existentes quedan en cero
     * hasta que se vuelva a cargar la pasiva. El índice único ignora techos retirados.
     */
    public function up(): void
    {
        Schema::table('techos', function (Blueprint $table) {
            $table->dropUnique('techos_llave_unica');
            $table->decimal('comprometido_pasiva', 20, 2)->default(0);
            $table->decimal('comprometido_ajuste', 20, 2)->nullable();
            $table->decimal('comprometido', 20, 2)->default(0);
            $table->decimal('obligado_pasiva', 20, 2)->default(0);
            $table->decimal('obligado_ajuste', 20, 2)->nullable();
            $table->decimal('obligado', 20, 2)->default(0);
            $table->decimal('pagado_pasiva', 20, 2)->default(0);
            $table->decimal('pagado_ajuste', 20, 2)->nullable();
            $table->decimal('pagado', 20, 2)->default(0);
            $table->softDeletes();
        });

        Schema::table('techo_historial', function (Blueprint $table) {
            $table->decimal('comprometido_anterior', 20, 2)->nullable();
            $table->decimal('comprometido_nuevo', 20, 2)->nullable();
            $table->decimal('obligado_anterior', 20, 2)->nullable();
            $table->decimal('obligado_nuevo', 20, 2)->nullable();
            $table->decimal('pagado_anterior', 20, 2)->nullable();
            $table->decimal('pagado_nuevo', 20, 2)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX techos_llave_unica ON techos (seguimiento_id, proyecto_id, fuente_financiacion_id, dependencia_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS techos_llave_unica');
        DB::table('techos')->whereNotNull('deleted_at')->delete();

        Schema::table('techo_historial', function (Blueprint $table) {
            $table->dropColumn([
                'comprometido_anterior',
                'comprometido_nuevo',
                'obligado_anterior',
                'obligado_nuevo',
                'pagado_anterior',
                'pagado_nuevo',
            ]);
        });

        Schema::table('techos', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'comprometido_pasiva',
                'comprometido_ajuste',
                'comprometido',
                'obligado_pasiva',
                'obligado_ajuste',
                'obligado',
                'pagado_pasiva',
                'pagado_ajuste',
                'pagado',
            ]);
            $table->unique(['seguimiento_id', 'proyecto_id', 'fuente_financiacion_id', 'dependencia_id'], 'techos_llave_unica');
        });
    }
};

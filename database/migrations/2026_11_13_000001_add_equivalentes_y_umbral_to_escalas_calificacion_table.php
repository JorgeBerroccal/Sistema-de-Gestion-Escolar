<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Equivalentes numéricos y umbral de aprobación por escala (research R-03). Son configuración de la
     * institución, no una regla del Currículo Nacional: un valor inicial razonable para poder promediar.
     */
    public function up(): void
    {
        Schema::table('escalas_calificacion', function (Blueprint $table) {
            $table->json('equivalentes')->nullable()->after('valores');
            $table->string('umbral_aprobacion', 8)->nullable()->after('equivalentes');
        });

        DB::table('escalas_calificacion')->where('codigo', 'literal')->whereNull('equivalentes')->update([
            'equivalentes' => json_encode(['AD' => 4, 'A' => 3, 'B' => 2, 'C' => 1]),
            'umbral_aprobacion' => 'B',
        ]);
        DB::table('escalas_calificacion')->where('codigo', 'vigesimal')->whereNull('umbral_aprobacion')->update([
            'umbral_aprobacion' => '11',
        ]);
    }

    public function down(): void
    {
        Schema::table('escalas_calificacion', function (Blueprint $table) {
            $table->dropColumn(['equivalentes', 'umbral_aprobacion']);
        });
    }
};

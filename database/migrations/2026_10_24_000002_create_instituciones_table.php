<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('nombre', 150);
            $table->string('codigo_modular', 20);
            $table->string('ugel_dre', 150);
            $table->foreignId('escala_calificacion_id')->nullable()->constrained('escalas_calificacion')->restrictOnDelete();
            $table->timestamps();
        });

        // Una sola ficha por instalación (research R-04).
        DB::statement('ALTER TABLE instituciones ADD CONSTRAINT instituciones_una_sola CHECK (id = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones');
    }
};

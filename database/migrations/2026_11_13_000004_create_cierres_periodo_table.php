<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_periodo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos_evaluacion')->restrictOnDelete();
            $table->foreignId('cerrado_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('cerrado_en');
            $table->timestamps();

            // Fila presente = periodo cerrado y publicado; reabrir la elimina (R-05).
            $table->unique(['seccion_id', 'periodo_id']);
            $table->index('periodo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_periodo');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos_evaluacion')->restrictOnDelete();
            $table->string('nombre', 60);
            // Los pesos de un (sección, curso, periodo) suman 100,00: regla de dominio, no de base (R-01).
            $table->decimal('peso', 5, 2);
            $table->unsignedTinyInteger('orden');
            $table->timestamps();

            $table->unique(['seccion_id', 'curso_id', 'periodo_id', 'nombre'], 'criterios_unico_nombre');
            $table->index(['seccion_id', 'curso_id', 'periodo_id', 'orden'], 'criterios_orden');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_evaluacion');
    }
};

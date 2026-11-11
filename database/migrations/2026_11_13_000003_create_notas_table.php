<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            // Eliminar un criterio borra sus notas (R-11).
            $table->foreignId('criterio_id')->constrained('criterios_evaluacion')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->string('valor', 8);
            $table->decimal('valor_numerico', 5, 2);
            $table->foreignId('registrado_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();

            // Una nota por criterio y alumno: el guardado es un `upsert` (R-02). Celda vacía = sin fila.
            $table->unique(['criterio_id', 'alumno_id']);
            $table->index('alumno_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};

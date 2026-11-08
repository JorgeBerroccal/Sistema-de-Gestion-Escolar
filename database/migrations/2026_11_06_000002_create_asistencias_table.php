<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->date('fecha');
            $table->foreignId('estado_id')->constrained('estados_asistencia')->restrictOnDelete();
            $table->string('observacion', 255)->nullable();
            $table->string('motivo_justificacion', 255)->nullable();
            $table->foreignId('registrado_por')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['seccion_id', 'alumno_id', 'fecha']);
            $table->index(['alumno_id', 'fecha']);
            $table->index(['seccion_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};

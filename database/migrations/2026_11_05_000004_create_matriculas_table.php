<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            // Copia del año de la sección, fijada al crear (la sección de una matrícula no cambia).
            // La FK es restrict: MySQL no admite CASCADE/SET NULL sobre la base de una columna generada.
            $table->foreignId('anio_id')->constrained('anios_escolares')->restrictOnDelete();
            $table->date('fecha_matricula');
            $table->string('estado', 12);
            $table->date('fecha_estado')->nullable();
            $table->string('motivo', 255)->nullable();
            // El año solo mientras la matrícula está activa y NULL en las demás: MySQL admite varios NULL
            // en un índice único, así la base impide dos matrículas activas del alumno en un año (R-01).
            $table->unsignedBigInteger('anio_activa_id')->nullable()
                ->storedAs("IF(estado = 'matriculado', anio_id, NULL)");
            $table->timestamps();

            $table->unique(['alumno_id', 'anio_activa_id']);
            $table->index(['seccion_id', 'estado']);
            $table->index(['alumno_id', 'anio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};

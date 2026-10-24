<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('secciones')->restrictOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['seccion_id', 'curso_id']);
            $table->index(['usuario_id', 'seccion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_docentes');
    }
};

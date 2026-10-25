<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nivel_id')->constrained('niveles')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(['nivel_id', 'nombre']);
        });

        Schema::create('curso_grado', function (Blueprint $table) {
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->primary(['curso_id', 'grado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_grado');
        Schema::dropIfExists('cursos');
    }
};

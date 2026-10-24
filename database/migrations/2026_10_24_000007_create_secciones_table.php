<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->foreignId('anio_escolar_id')->constrained('anios_escolares')->restrictOnDelete();
            $table->string('identificador', 10);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(['grado_id', 'anio_escolar_id', 'identificador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones');
    }
};

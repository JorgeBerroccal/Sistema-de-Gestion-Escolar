<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anio_escolar_id')->constrained('anios_escolares')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('nombre', 40);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestamps();

            $table->unique(['anio_escolar_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_evaluacion');
    }
};

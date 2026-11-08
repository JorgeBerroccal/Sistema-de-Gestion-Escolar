<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_asistencia', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 40);
            $table->boolean('cuenta_asistencia')->default(false);
            $table->boolean('cuenta_falta')->default(false);
            // Estado al que pasa este al justificarse (falta → falta justificada).
            $table->foreignId('justificado_id')->nullable()->constrained('estados_asistencia')->restrictOnDelete();
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_asistencia');
    }
};

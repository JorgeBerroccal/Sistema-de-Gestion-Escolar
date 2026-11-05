<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->string('numero_documento', 20)->index();
            $table->date('fecha_nacimiento');
            $table->string('sexo', 1);
            $table->foreignId('usuario_id')->nullable()->unique()->constrained('usuarios')->restrictOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->index(['apellidos', 'nombres']);
            // El documento es único por tipo y número (FR-003), también ante operaciones simultáneas.
            $table->unique(['tipo_documento_id', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};

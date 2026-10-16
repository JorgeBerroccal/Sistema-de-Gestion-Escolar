<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SOLO PARA ENSAYO (spec 002, FR-031): DDL parcial seguido de excepción. Nunca se integra a main.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('columna_prueba_falla')->nullable();
        });

        throw new RuntimeException('Migración defectuosa de ensayo (v0.2.9-prueba-falla).');
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('columna_prueba_falla');
        });
    }
};

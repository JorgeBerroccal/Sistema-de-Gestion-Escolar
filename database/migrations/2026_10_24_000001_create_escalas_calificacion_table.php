<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalas_calificacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 60);
            $table->json('valores');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalas_calificacion');
    }
};

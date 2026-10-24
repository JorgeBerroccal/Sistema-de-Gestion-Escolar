<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anios_escolares', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 30)->unique();
            $table->date('fecha_inicio')->index();
            $table->date('fecha_fin');
            $table->string('tipo_periodo', 15);
            // 1 en el año vigente y NULL en los demás: MySQL admite varios NULL en un índice único,
            // así la base impide dos años vigentes (research R-01).
            $table->unsignedTinyInteger('vigente')->nullable()->unique();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anios_escolares');
    }
};

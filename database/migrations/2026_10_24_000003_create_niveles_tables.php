<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->unsignedSmallInteger('orden')->unique();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('institucion_nivel', function (Blueprint $table) {
            $table->unsignedTinyInteger('institucion_id');
            $table->foreignId('nivel_id')->constrained('niveles')->restrictOnDelete();
            $table->primary(['institucion_id', 'nivel_id']);
            $table->foreign('institucion_id')->references('id')->on('instituciones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institucion_nivel');
        Schema::dropIfExists('niveles');
    }
};

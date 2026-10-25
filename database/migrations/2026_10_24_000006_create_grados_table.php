<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nivel_id')->constrained('niveles')->restrictOnDelete();
            $table->string('nombre', 40);
            $table->unsignedSmallInteger('orden');
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(['nivel_id', 'nombre']);
            $table->unique(['nivel_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grados');
    }
};

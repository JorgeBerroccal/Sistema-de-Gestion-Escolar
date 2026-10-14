<?php

use App\Autorizacion\Pertenencia\ReglaPertenenciaFaltante;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Pertenencia\ModeloSinPertenencia;
use Tests\Support\Pertenencia\ReglaDePrueba;
use Tests\Support\Pertenencia\RegistroDePrueba;

beforeEach(function () {
    Schema::create('registros_de_prueba', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('usuario_id');
        $t->string('titulo');
    });
    Schema::create('modelos_sin_pertenencia', function (Blueprint $t) {
        $t->id();
        $t->string('titulo');
    });

    $this->ana = Usuario::factory()->create();
    $this->beto = Usuario::factory()->create();
    RegistroDePrueba::create(['usuario_id' => $this->ana->id, 'titulo' => 'de Ana']);
    $this->ajeno = RegistroDePrueba::create(['usuario_id' => $this->beto->id, 'titulo' => 'de Beto']);
});

afterEach(function () {
    Schema::dropIfExists('registros_de_prueba');
    Schema::dropIfExists('modelos_sin_pertenencia');
});

function conRegla(): void
{
    config(['pertenencia.reglas' => [RegistroDePrueba::class => ReglaDePrueba::class]]);
}

it('con regla, visiblePara devuelve solo los registros del usuario', function () {
    conRegla();

    expect(RegistroDePrueba::query()->visiblePara($this->ana)->pluck('titulo')->all())->toBe(['de Ana']);
});

it('con regla, un registro ajeno da 403 y uno propio no', function () {
    conRegla();

    expect(fn () => $this->ajeno->autorizarPertenencia($this->ana))
        ->toThrow(Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
    $this->ajeno->autorizarPertenencia($this->beto);
});

it('con regla, un usuario sin registros propios obtiene una colección vacía', function () {
    conRegla();
    $nuevo = Usuario::factory()->create();

    expect(RegistroDePrueba::query()->visiblePara($nuevo)->get())->toBeEmpty();
});

it('sin regla, visiblePara lanza ReglaPertenenciaFaltante', function () {
    expect(fn () => RegistroDePrueba::query()->visiblePara($this->ana)->get())
        ->toThrow(ReglaPertenenciaFaltante::class);
});

it('sin regla, una ruta que consulta responde 403', function () {
    Route::middleware('web')->get('/prueba-pertenencia', fn () => RegistroDePrueba::query()->visiblePara(auth()->user())->get());

    $this->actingAs($this->ana)->get('/prueba-pertenencia')->assertForbidden();
});

it('sin regla, autorizarPertenencia da 403', function () {
    expect(fn () => $this->ajeno->autorizarPertenencia($this->beto))->toThrow(ReglaPertenenciaFaltante::class);
});

it('un modelo sin el trait no se altera', function () {
    ModeloSinPertenencia::create(['titulo' => 'a']);
    ModeloSinPertenencia::create(['titulo' => 'b']);

    expect(ModeloSinPertenencia::query()->count())->toBe(2)
        ->and(method_exists(ModeloSinPertenencia::class, 'scopeVisiblePara'))->toBeFalse();
});

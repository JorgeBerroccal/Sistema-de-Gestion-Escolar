<?php

use App\Livewire\Asistente\Chat\Index;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('ningún mensaje ni respuesta queda en base de datos, caché ni registros', function () {
    $e = escenarioAsistente();
    $marca = 'MARCA-UNICA-7731';
    File::put(storage_path('logs/laravel.log'), '');

    $antes = collect(DB::select('SHOW TABLES'))->mapWithKeys(fn ($t) => [array_values((array) $t)[0] => null])
        ->map(fn ($_, $tabla) => DB::table($tabla)->count());

    Livewire::actingAs($e['alumno_usuario'])->test(Index::class)->set('mensaje', "hola $marca")->call('enviar');

    $despues = $antes->map(fn ($_, $tabla) => DB::table($tabla)->count());
    $volcado = collect(DB::select('SHOW TABLES'))->flatMap(fn ($t) => DB::table(array_values((array) $t)[0])->get()->map(fn ($f) => json_encode($f)))->implode("\n");

    expect($despues->all())->toBe($antes->all())
        ->and($volcado)->not->toContain($marca)->not->toContain('Respuesta de prueba')
        ->and(Cache::get($marca))->toBeNull()
        ->and(File::get(storage_path('logs/laravel.log')))->not->toContain($marca)->not->toContain('Respuesta de prueba');
});

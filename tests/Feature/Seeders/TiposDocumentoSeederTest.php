<?php

use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentoSeeder;

it('carga DNI, CE y otro con sus reglas de formato', function () {
    $this->seed(TiposDocumentoSeeder::class);

    $dni = TipoDocumento::where('codigo', 'DNI')->first();
    expect($dni->longitud_min)->toBe(8)->and($dni->longitud_max)->toBe(8)->and($dni->solo_digitos)->toBeTrue()
        ->and(TipoDocumento::where('codigo', 'CE')->first()->longitud_min)->toBe(9)
        ->and(TipoDocumento::where('codigo', 'OTRO')->first()->longitud_max)->toBe(20);
});

it('es idempotente y no pisa cambios hechos después', function () {
    $this->seed(TiposDocumentoSeeder::class);
    TipoDocumento::where('codigo', 'OTRO')->update(['activo' => false]);

    $this->seed(TiposDocumentoSeeder::class);

    expect(TipoDocumento::count())->toBe(3)
        ->and(TipoDocumento::where('codigo', 'OTRO')->value('activo'))->toBeFalse();
});

it('valida el formato de cada tipo', function () {
    $this->seed(TiposDocumentoSeeder::class);
    $dni = TipoDocumento::where('codigo', 'DNI')->first();
    $ce = TipoDocumento::where('codigo', 'CE')->first();

    expect($dni->errorDeFormato('12345678'))->toBeNull()
        ->and($dni->errorDeFormato('1234567'))->toContain('exactamente 8')
        ->and($dni->errorDeFormato('123456789'))->toContain('exactamente 8')
        ->and($dni->errorDeFormato('1234567A'))->toContain('solo dígitos')
        ->and($ce->errorDeFormato('AB1234567'))->toBeNull()
        ->and($ce->errorDeFormato('AB12'))->toContain('entre 9 y 12')
        ->and($ce->errorDeFormato('AB-123456'))->toContain('letras y dígitos');
});

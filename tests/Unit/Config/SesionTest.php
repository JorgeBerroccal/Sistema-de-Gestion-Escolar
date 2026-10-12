<?php

it('la sesión dura 120 minutos y no expira al cerrar el navegador', function () {
    expect(config('session.lifetime'))->toBe(120)
        ->and(config('session.expire_on_close'))->toBeFalse();
});

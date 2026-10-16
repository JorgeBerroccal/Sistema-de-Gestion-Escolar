<?php

// Prueba DELIBERADAMENTE rota para verificar que el CI la marca en rojo (T019). No integrar.
it('falla a propósito para comprobar que el CI la detecta', function () {
    expect(1 + 1)->toBe(3);
});

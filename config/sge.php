<?php

return [
    // Administrador inicial (AdministradorInicialSeeder; research R-10).
    'admin' => [
        'username' => env('SGE_ADMIN_USERNAME'),
        'email' => env('SGE_ADMIN_EMAIL'),
        'password' => env('SGE_ADMIN_PASSWORD'),
    ],

    // Contraseña de las cuentas de la prueba de carga (LoadTestSeeder, EvaluacionCargaSeeder). Obligatoria fuera de
    // local y testing; nunca en el repositorio (research R-22 del Módulo 5).
    'carga' => [
        'password' => env('CARGA_PASSWORD'),
    ],
];

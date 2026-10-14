<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reglas de pertenencia
    |--------------------------------------------------------------------------
    |
    | Asocia cada modelo que usa el trait RequierePertenencia con su regla. Un modelo con el trait
    | y sin regla registrada se deniega siempre (403).
    |
    */

    'reglas' => [
        // App\Models\Alumno::class => App\Autorizacion\Pertenencia\Reglas\AlumnoRegla::class,
    ],

];

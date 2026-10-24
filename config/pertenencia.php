<?php

use App\Autorizacion\Pertenencia\Reglas\AsignacionDocenteRegla;
use App\Models\AsignacionDocente;

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
        AsignacionDocente::class => AsignacionDocenteRegla::class,
        // App\Models\Alumno::class => App\Autorizacion\Pertenencia\Reglas\AlumnoRegla::class,
    ],

];

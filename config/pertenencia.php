<?php

use App\Autorizacion\Pertenencia\Reglas\AlumnoRegla;
use App\Autorizacion\Pertenencia\Reglas\AsignacionDocenteRegla;
use App\Autorizacion\Pertenencia\Reglas\AsistenciaRegla;
use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\Matricula;

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
        Alumno::class => AlumnoRegla::class,
        Matricula::class => MatriculaRegla::class,
        Asistencia::class => AsistenciaRegla::class,
    ],

];

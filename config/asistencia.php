<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de la institución
    |--------------------------------------------------------------------------
    |
    | Define qué día es «hoy» al registrar asistencia (no se admiten fechas futuras). Es independiente de
    | `app.timezone`, que sigue en UTC. Cada institución la ajusta con ASISTENCIA_ZONA_HORARIA.
    |
    */

    'zona_horaria' => env('ASISTENCIA_ZONA_HORARIA', 'America/Lima'),

];

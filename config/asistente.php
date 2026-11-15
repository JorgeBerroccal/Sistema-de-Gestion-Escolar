<?php

/*
|--------------------------------------------------------------------------
| Asistente virtual (Módulo 6)
|--------------------------------------------------------------------------
|
| Proveedor de IA intercambiable (Constitución 2.3.0, Principio VI): el adaptador y el modelo se eligen por
| entorno. Las claves van solo en `.env` y nunca se versionan.
|
*/

return [

    // `falso` (pruebas y desarrollo, no sale a la red) | `openrouter`.
    'proveedor' => env('ASISTENTE_PROVEEDOR', 'falso'),

    'openrouter' => [
        'clave' => env('OPENROUTER_API_KEY'),
        'url' => env('ASISTENTE_OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
        'modelo' => env('ASISTENTE_OPENROUTER_MODELO'),
        // Lista separada por comas; los modelos gratuitos rotan.
        'respaldo' => array_values(array_filter(array_map('trim', explode(',', (string) env('ASISTENTE_OPENROUTER_RESPALDO', ''))))),
        // Solo `true` cuando los modelos configurados son de pago y contractualmente aptos para datos reales.
        'datos_reales' => (bool) env('ASISTENTE_OPENROUTER_DATOS_REALES', false),
        'timeout' => (int) env('ASISTENTE_TIMEOUT', 20),
    ],

    // Plan gratuito del proveedor: 50 solicitudes por día y 20 por minuto en total (con margen).
    'limites' => [
        'usuario_minuto' => (int) env('ASISTENTE_LIMITE_USUARIO_MINUTO', 3),
        'usuario_dia' => (int) env('ASISTENTE_LIMITE_USUARIO_DIA', 15),
        'global_minuto' => (int) env('ASISTENTE_LIMITE_GLOBAL_MINUTO', 18),
        'global_dia' => (int) env('ASISTENTE_LIMITE_GLOBAL_DIA', 45),
    ],

    'mensaje' => [
        'maximo' => (int) env('ASISTENTE_MENSAJE_MAXIMO', 500),
    ],

    'conversacion' => [
        'turnos' => (int) env('ASISTENTE_TURNOS', 6),
    ],

];

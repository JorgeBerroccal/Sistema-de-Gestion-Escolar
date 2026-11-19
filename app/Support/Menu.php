<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\Route;

/**
 * Menú lateral e Inicio con tarjetas: una sola fuente (`config/menu.php`) filtrada por los permisos del usuario,
 * de modo que menú e Inicio nunca difieren. Nunca consulta nombres de rol (SC-003 y SC-005).
 */
class Menu
{
    /** Orden de los grupos y su ícono (Heroicons de components/icono). */
    public const GRUPOS = [
        'Administración' => 'shield-check',
        'Gestión académica' => 'academic-cap',
        'Matrícula' => 'identification',
        'Asistencia' => 'clipboard-document-check',
        'Evaluación' => 'document-text',
        'Asistente' => 'chat-bubble-left-right',
    ];

    /** @return list<array<string, mixed>> los ítems del menú visibles para el usuario, en el orden de `config/menu.php`. */
    public static function itemsPara(Usuario $usuario): array
    {
        return array_values(array_filter(
            config('menu'),
            fn (array $item) => Route::has($item['ruta']) && ($item['permiso'] === null || $usuario->can($item['permiso'])),
        ));
    }

    /**
     * @return list<array{nombre: string, icono: string, items: list<array<string, mixed>>}>
     */
    public static function gruposPara(Usuario $usuario): array
    {
        $items = self::itemsPara($usuario);
        $grupos = [];

        foreach (self::GRUPOS as $nombre => $icono) {
            $delGrupo = array_values(array_filter($items, fn (array $i) => $i['grupo'] === $nombre));
            if ($delGrupo !== []) {
                $grupos[] = ['nombre' => $nombre, 'icono' => $icono, 'items' => $delGrupo];
            }
        }

        return $grupos;
    }

    /** Los ítems sueltos (sin grupo), como Inicio. */
    public static function sueltosPara(Usuario $usuario): array
    {
        return array_values(array_filter(self::itemsPara($usuario), fn (array $i) => $i['grupo'] === null));
    }

    /** Si el ítem corresponde a la pantalla actual (un `.index` abarca sus pantallas hermanas: crear, editar). */
    public static function esActivo(array $item): bool
    {
        $ruta = str_ends_with($item['ruta'], '.index') ? substr($item['ruta'], 0, -5).'*' : $item['ruta'];

        return request()->routeIs($ruta);
    }
}

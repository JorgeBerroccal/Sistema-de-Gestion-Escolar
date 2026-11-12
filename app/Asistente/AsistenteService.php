<?php

namespace App\Asistente;

use App\Asistente\Proveedores\ProveedorIA;
use App\Asistente\Proveedores\ProveedorNoDisponible;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta una pregunta: validación → (disponibilidad, fase siguiente) → alumno → límite → contexto → proveedor.
 * La aclaración y el «sin datos» se resuelven antes del límite: no llaman al proveedor y no consumen cupo.
 * El contenido de mensajes y respuestas nunca se registra: solo metadatos (usuario, adaptador, resultado).
 */
class AsistenteService
{
    public function __construct(
        private readonly ProveedorIA $proveedor,
        private readonly ResolverAlumno $alumnos,
        private readonly ConstructorContexto $contexto,
        private readonly LimiteSolicitudes $limite,
    ) {}

    /**
     * @param  list<array{rol: string, texto: string}>  $turnos  turnos previos de la sesión (`usuario` | `asistente`)
     *
     * @throws AuthorizationException sin el permiso `asistente.usar`
     */
    public function preguntar(Usuario $usuario, string $mensaje, array $turnos = []): ResultadoAsistente
    {
        if (! $usuario->tienePermiso('asistente.usar')) {
            throw new AuthorizationException('No tienes acceso al asistente virtual.');
        }

        $mensaje = trim($mensaje);
        $maximo = (int) config('asistente.mensaje.maximo');

        if ($mensaje === '') {
            return $this->resultado($usuario, new ResultadoAsistente(ResultadoAsistente::INVALIDO, 'Escribe tu pregunta para poder ayudarte.'));
        }

        if (mb_strlen($mensaje) > $maximo) {
            return $this->resultado($usuario, new ResultadoAsistente(ResultadoAsistente::INVALIDO, "Tu mensaje es demasiado largo. Usa como máximo {$maximo} caracteres."));
        }

        $resolucion = $this->alumnos->resolver($usuario, $mensaje);

        if ($resolucion->sinDatos !== null) {
            return $this->resultado($usuario, new ResultadoAsistente(ResultadoAsistente::SIN_DATOS, $resolucion->sinDatos));
        }

        if ($resolucion->aclaracion !== null) {
            return $this->resultado($usuario, new ResultadoAsistente(ResultadoAsistente::ACLARACION, $resolucion->aclaracion));
        }

        if (($espera = $this->limite->intentar($usuario)) !== null) {
            return $this->resultado($usuario, new ResultadoAsistente(
                ResultadoAsistente::LIMITE,
                'Has alcanzado el límite de preguntas por ahora. Podrás volver a preguntar en '.LimiteSolicitudes::textoEspera($espera).'.',
                $espera,
            ));
        }

        $mensajes = [
            ['rol' => 'system', 'texto' => Instrucciones::mensaje()],
            ['rol' => 'system', 'texto' => $this->contexto->construir($usuario, $resolucion->alumno)],
            ...$this->historial($turnos),
            ['rol' => 'user', 'texto' => $mensaje],
        ];

        try {
            $respuesta = $this->proveedor->responder($mensajes);
        } catch (ProveedorNoDisponible) {
            return $this->resultado($usuario, new ResultadoAsistente(ResultadoAsistente::ERROR, 'El asistente no está disponible en este momento. Inténtalo de nuevo más tarde; el resto del sistema sigue funcionando.'));
        }

        return $this->resultado($usuario, new ResultadoAsistente(ResultadoAsistente::RESPUESTA, $respuesta->texto));
    }

    /**
     * Últimos turnos de la sesión con los roles del proveedor.
     *
     * @param  list<array{rol: string, texto: string}>  $turnos
     * @return list<array{rol: string, texto: string}>
     */
    private function historial(array $turnos): array
    {
        $limite = max(0, (int) config('asistente.conversacion.turnos'));

        if ($limite === 0) {
            return [];
        }

        return collect($turnos)
            ->filter(fn ($t) => in_array($t['rol'] ?? null, ['usuario', 'asistente'], true) && is_string($t['texto'] ?? null))
            ->map(fn ($t) => ['rol' => $t['rol'] === 'usuario' ? 'user' : 'assistant', 'texto' => $t['texto']])
            ->slice(-$limite)->values()->all();
    }

    private function resultado(Usuario $usuario, ResultadoAsistente $resultado): ResultadoAsistente
    {
        Log::info('asistente', ['usuario' => $usuario->id, 'adaptador' => $this->proveedor->nombre(), 'resultado' => $resultado->tipo]);

        return $resultado;
    }
}

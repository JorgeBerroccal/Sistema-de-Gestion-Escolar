<?php

namespace App\Livewire\Asistente\Chat;

use App\Asistente\AsistenteService;
use App\Asistente\ResultadoAsistente;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Chat del asistente virtual. La conversación vive solo en el estado de este componente: no se guarda en base de
 * datos, caché ni registros (decisión 1 del Módulo 6); recargar la página la reinicia.
 */
class Index extends Component
{
    public string $mensaje = '';

    /** @var list<array{rol: string, texto: string, tipo: string}> */
    public array $turnos = [];

    public function mount(): void
    {
        $this->autorizar();
    }

    public function enviar(AsistenteService $servicio): void
    {
        $this->autorizar();

        $texto = trim($this->mensaje);
        $previos = array_values(array_filter(
            array_map(fn (array $t) => ['rol' => $t['rol'], 'texto' => $t['texto'], 'tipo' => $t['tipo']], $this->turnos),
            fn (array $t) => $t['rol'] === 'usuario' || $t['tipo'] === ResultadoAsistente::RESPUESTA,
        ));

        try {
            $resultado = $servicio->preguntar(Auth::user(), $texto, $previos);
        } catch (AuthorizationException) {
            throw new AccessDeniedHttpException('No tienes acceso al asistente virtual.');
        }

        if ($resultado->tipo !== ResultadoAsistente::INVALIDO) {
            $this->turnos[] = ['rol' => 'usuario', 'texto' => $texto, 'tipo' => 'usuario'];
        }

        $this->turnos[] = ['rol' => 'asistente', 'texto' => $resultado->texto, 'tipo' => $resultado->tipo];
        $this->turnos = array_slice($this->turnos, -40);
        $this->mensaje = '';
    }

    public function render(): View
    {
        return view('livewire.asistente.chat.index');
    }

    private function autorizar(): void
    {
        if (! Auth::user()->tienePermiso('asistente.usar')) {
            throw new AccessDeniedHttpException('No tienes acceso al asistente virtual.');
        }
    }
}

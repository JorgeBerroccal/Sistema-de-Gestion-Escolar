<?php

namespace App\Asistente;

use App\Asistencia\ResumenAsistencia;
use App\Evaluacion\ConsultaNotas;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Support\Formato;
use App\Support\ZonaHoraria;
use Illuminate\Support\Collection;

/**
 * Contexto mínimo que recibe el proveedor para un alumno (research R-07 y R-13): solo nombres, cursos, notas
 * publicadas, asistencia, sección y matrícula. Cada bloque se incluye solo si el usuario tiene el permiso de su
 * pantalla, y se reutilizan las consultas filtradas de las pantallas. Documento, fecha de nacimiento, correos e
 * ids no se leen ni se escriben.
 */
class ConstructorContexto
{
    public function __construct(
        private readonly ConsultaNotas $notas,
        private readonly ResumenAsistencia $asistencia,
    ) {}

    public function construir(Usuario $usuario, Alumno $alumno): string
    {
        $lineas = ['CONTEXTO (único dato disponible; no hay más información):', 'Estudiante: '.$alumno->nombres.' '.$alumno->apellidos];

        array_push($lineas, ...$this->matricula($usuario, $alumno));
        array_push($lineas, ...$this->notas($usuario, $alumno));
        array_push($lineas, ...$this->asistencia($usuario, $alumno));

        return implode("\n", $lineas);
    }

    /** @return list<string> */
    private function matricula(Usuario $usuario, Alumno $alumno): array
    {
        if (! $usuario->tienePermiso('matricula.ver_propia')) {
            return ['Matrícula y sección: el usuario no tiene acceso a esta información.'];
        }

        $matriculas = Matricula::query()->with(['seccion.grado.nivel', 'anio'])
            ->where('alumno_id', $alumno->id)->orderByDesc('fecha_matricula')->orderByDesc('id')->limit(3)->get();

        if ($matriculas->isEmpty()) {
            return ['Matrícula y sección: el estudiante no tiene matrícula registrada.'];
        }

        return $matriculas->map(fn (Matricula $m) => "Matrícula {$m->anio->nombre}: {$m->seccion->etiqueta}, estado {$m->estado->etiqueta()}.")->all();
    }

    /** @return list<string> */
    private function notas(Usuario $usuario, Alumno $alumno): array
    {
        if (! $usuario->tienePermiso('notas.ver_propia')) {
            return ['Notas: el usuario no tiene acceso a esta información.'];
        }

        $lineas = [];

        foreach ($this->notas->periodosPublicados($alumno)->take(8) as $periodo) {
            $datos = $this->notas->delAlumno($alumno, $periodo, $usuario);

            if (! $datos) {
                continue;
            }

            $lineas[] = "Notas del periodo {$periodo->nombre} ({$periodo->anio->nombre}), publicado. Promedio general: ".($datos['etiqueta'] ?? 'sin notas').'.';

            foreach ($datos['cursos'] as $fila) {
                $criterios = $fila['criterios']->filter(fn ($d) => $d['nota'])
                    ->map(fn ($d) => "{$d['criterio']->nombre} ({$d['criterio']->peso} %): {$d['nota']->valor}")->implode('; ');

                $lineas[] = "- {$fila['curso']->nombre}: promedio ".($fila['etiqueta'] ?? 'sin notas').($criterios !== '' ? "; $criterios" : '').'.';
            }
        }

        return $lineas ?: ['Notas: ningún periodo publicado todavía.'];
    }

    /** @return list<string> */
    private function asistencia(Usuario $usuario, Alumno $alumno): array
    {
        if (! $usuario->tienePermiso('asistencia.ver_propia')) {
            return ['Asistencia: el usuario no tiene acceso a esta información.'];
        }

        $hoy = ZonaHoraria::ahora();
        $vigente = AnioEscolar::query()->where('vigente', 1)->first();
        $desde = $vigente?->fecha_inicio ?? $hoy->startOfYear();
        $hasta = $vigente?->fecha_fin ?? $hoy->endOfYear();

        $lineas = [$this->lineaAsistencia('Asistencia del año'.($vigente ? " {$vigente->nombre}" : ''), $this->asistencia->delAlumno($alumno, $desde, $hasta))];
        $lineas[] = $this->lineaAsistencia('Asistencia del mes en curso', $this->asistencia->delAlumno($alumno, $hoy->startOfMonth(), $hoy->endOfMonth()));

        return $lineas;
    }

    /** @param array{por_estado: Collection, faltas: int, registrados: int, porcentaje: float|null} $resumen */
    private function lineaAsistencia(string $titulo, array $resumen): string
    {
        if ($resumen['registrados'] === 0) {
            return "$titulo: sin registros de asistencia.";
        }

        $estados = $resumen['por_estado']->map(fn ($f) => "{$f->nombre} {$f->total}")->implode(', ');
        $porcentaje = $resumen['porcentaje'] === null ? 'sin datos' : Formato::recortado($resumen['porcentaje']).' %';

        return "$titulo: $estados; faltas: {$resumen['faltas']}; porcentaje de asistencia: $porcentaje.";
    }
}

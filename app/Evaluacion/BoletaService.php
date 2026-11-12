<?php

namespace App\Evaluacion;

use App\Asistencia\ResumenAsistencia;
use App\Models\Alumno;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use App\Support\ZonaHoraria;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Boleta de un alumno por periodo o anual, en un único formato (research R-13, R-14). Este servicio NO decide
 * quién puede verla: el controlador autoriza antes (permisos `boletas.*`, vínculo y publicación) y aquí se lee con
 * `ConsultaNotas` sin pasar por las reglas de pertenencia de notas, de modo que la boleta incluye todos los cursos.
 */
class BoletaService
{
    public function __construct(
        private readonly ConsultaNotas $consulta,
        private readonly EvaluacionService $evaluacion,
        private readonly ResumenAsistencia $asistencia,
    ) {}

    /**
     * Datos de la boleta, o `null` si el alumno no tiene matrícula en el año del periodo (o, con
     * `$soloPublicados`, ninguna sección con el periodo cerrado).
     *
     * @param  PeriodoEvaluacion|null  $periodo  `null` = anual (ver `ConsultaNotas::periodosDelAnioAnual`)
     * @return array<string, mixed>|null
     */
    public function datos(Alumno $alumno, ?PeriodoEvaluacion $periodo, bool $soloPublicados): ?array
    {
        $calculo = $this->evaluacion->calculo();

        if ($periodo) {
            $seccionDelPeriodo = $this->consulta->seccionDelPeriodo($alumno, $periodo, $soloPublicados);

            if (! $seccionDelPeriodo) {
                return null;
            }

            $detalle = $this->consulta->delAlumno($alumno, $periodo, null, $seccionDelPeriodo);

            $anio = $periodo->anio_escolar_id;
            $periodos = collect([$periodo]);
            $cursos = $detalle['cursos'];
            $general = ['promedio' => $detalle['promedio'], 'etiqueta' => $detalle['etiqueta']];
            $titulo = $periodo->nombre;
            $desde = $periodo->fecha_inicio;
            $hasta = $periodo->fecha_fin;
        } else {
            $periodos = $this->consulta->periodosDelAnioAnual($alumno, $soloPublicados);

            if ($periodos->isEmpty()) {
                return null;
            }

            $anual = $this->consulta->anualDelAlumno($alumno, $periodos, null, $soloPublicados);

            if ($anual['periodos'] === []) {
                return null;
            }

            $anio = $periodos->first()->anio_escolar_id;
            $cursos = $anual['cursos'];
            $general = ['promedio' => $anual['promedio'], 'etiqueta' => $anual['etiqueta']];
            $titulo = 'Anual';
            $periodos = $periodos->whereIn('id', $anual['periodos'])->values();
            $desde = $periodos->first()->anio->fecha_inicio;
            $hasta = $periodos->first()->anio->fecha_fin;
        }

        $seccion = $this->seccionVigente($alumno, $anio);

        return [
            'institucion' => Institucion::actual(),
            'escala' => $this->evaluacion->escala(),
            'literal' => $calculo->esLiteral(),
            'estudiante' => $alumno,
            'seccion' => $seccion,
            'anio' => $periodos->first()->anio ?? $seccion?->anio,
            'modo' => $periodo ? 'periodo' : 'anual',
            'titulo' => $titulo,
            'periodos' => $periodos,
            'cursos' => $cursos,
            'general' => $general,
            'asistencia' => $this->asistencia->delAlumno($alumno, $desde, $hasta),
            'borrador' => $periodos->contains(fn (PeriodoEvaluacion $p) => ! $this->periodoPublicadoDelAlumno($alumno, $p)),
            'generado' => ZonaHoraria::ahora(),
            'etiquetar' => fn (?float $n) => $calculo->etiqueta($n),
        ];
    }

    /** PDF A4 de la boleta; no se guarda en disco (R-13). */
    public function pdf(array $datos): DomPdf
    {
        return Pdf::loadView('boletas.boleta', $datos)->setPaper('a4');
    }

    /** Nombre del archivo, sin datos personales. */
    public function nombreArchivo(Alumno $alumno, ?PeriodoEvaluacion $periodo): string
    {
        return 'boleta-'.$alumno->id.'-'.($periodo?->id ?? 'anual').'.pdf';
    }

    /** Sección en la que el alumno está matriculado hoy dentro del año (la más reciente). Spec: casos límite. */
    private function seccionVigente(Alumno $alumno, int $anioId): ?Seccion
    {
        $matricula = Matricula::query()->with(['seccion.grado.nivel', 'seccion.anio'])
            ->where('alumno_id', $alumno->id)->where('anio_id', $anioId)
            ->orderByRaw("CASE WHEN estado = 'matriculado' THEN 0 ELSE 1 END")->orderByDesc('fecha_matricula')->orderByDesc('id')
            ->first();

        return $matricula?->seccion;
    }

    /** El periodo está cerrado en la sección cuyas notas se usan para este alumno. */
    private function periodoPublicadoDelAlumno(Alumno $alumno, PeriodoEvaluacion $periodo): bool
    {
        $seccion = $this->consulta->seccionDelPeriodo($alumno, $periodo, soloPublicado: false);

        return $seccion !== null && $this->evaluacion->periodoCerrado($seccion, $periodo);
    }
}

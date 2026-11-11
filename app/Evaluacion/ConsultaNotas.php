<?php

namespace App\Evaluacion;

use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas de notas y promedios con consultas acotadas y `with()` (research R-12). Los promedios no se guardan:
 * se calculan al leer con `CalculoPromedios` y la escala de la institución.
 */
class ConsultaNotas
{
    public function __construct(private readonly EvaluacionService $evaluacion) {}

    /**
     * Notas de un (sección, curso, periodo) por alumno y criterio: `[alumno_id][criterio_id] => Nota`.
     *
     * @param  Collection<int, CriterioEvaluacion>  $criterios
     * @return array<int, array<int, Nota>>
     */
    public function notasDelPar(Collection $criterios): array
    {
        $porAlumno = [];

        foreach (Nota::query()->whereIn('criterio_id', $criterios->pluck('id'))->get() as $nota) {
            $porAlumno[$nota->alumno_id][$nota->criterio_id] = $nota;
        }

        return $porAlumno;
    }

    /**
     * Promedio del periodo por alumno para un par (sección, curso); `null` = «sin notas».
     *
     * @return array<int, float|null> por `alumno_id` de la nómina activa
     */
    public function promediosDelPar(Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo): array
    {
        $criterios = $this->evaluacion->criteriosDelPar($seccion, $curso, $periodo);
        $notas = $this->notasDelPar($criterios);
        $calculo = $this->evaluacion->calculo();
        $promedios = [];

        foreach ($this->evaluacion->nominaActiva($seccion) as $alumno) {
            $promedios[$alumno->id] = $calculo->promedioPonderado($criterios->map(fn (CriterioEvaluacion $c) => [
                'peso' => $c->peso,
                'nota' => isset($notas[$alumno->id][$c->id]) ? $notas[$alumno->id][$c->id]->valor_numerico : null,
            ]));
        }

        return $promedios;
    }

    /**
     * Periodos publicados (cerrados) de las secciones donde el alumno tiene o tuvo matrícula, del más reciente al más
     * antiguo año y por orden dentro del año. Una sola consulta con `with()`.
     *
     * @return Collection<int, PeriodoEvaluacion>
     */
    public function periodosPublicados(Alumno $alumno): Collection
    {
        $cerrados = CierrePeriodo::query()->select('periodo_id')
            ->whereIn('seccion_id', Matricula::query()->select('seccion_id')->where('alumno_id', $alumno->id));

        return PeriodoEvaluacion::query()->with('anio')->whereIn('id', $cerrados)
            ->orderByDesc(AnioEscolar::query()->select('fecha_inicio')->whereColumn('anios_escolares.id', 'periodos_evaluacion.anio_escolar_id'))
            ->orderBy('orden')->get();
    }

    /**
     * Periodos del «año anual» del alumno: el año escolar más reciente en el que tiene matrícula (y, con
     * `$soloPublicados`, algún periodo cerrado), con todos sus periodos o solo los publicados. Es lo que significa
     * `anual` en la pantalla de notas y en la ruta de la boleta.
     *
     * @return Collection<int, PeriodoEvaluacion>
     */
    public function periodosDelAnioAnual(Alumno $alumno, bool $soloPublicados = true): Collection
    {
        $aniosDelAlumno = Matricula::query()->select('anio_id')->where('alumno_id', $alumno->id);

        $publicados = $soloPublicados ? $this->periodosPublicados($alumno)->whereIn('anio_escolar_id', $aniosDelAlumno->pluck('anio_id')) : null;

        $anioId = $soloPublicados
            ? $publicados->first()?->anio_escolar_id
            : AnioEscolar::query()->whereIn('id', $aniosDelAlumno)->orderByDesc('fecha_inicio')->value('id');

        if (! $anioId) {
            return collect();
        }

        return PeriodoEvaluacion::query()->with('anio')->where('anio_escolar_id', $anioId)
            ->when($soloPublicados, fn ($q) => $q->whereIn('id', $publicados->pluck('id')))
            ->orderBy('orden')->get();
    }

    /**
     * Sección cuyas notas corresponden al alumno en un periodo: entre sus matrículas del año (cerradas, si
     * `$soloPublicado`), la que tiene notas suyas y, si ninguna, la más reciente. En un traslado a mitad de año las
     * notas quedan en la sección original (spec, casos límite).
     */
    public function seccionDelPeriodo(Alumno $alumno, PeriodoEvaluacion $periodo, bool $soloPublicado = true): ?Seccion
    {
        $secciones = Matricula::query()->where('alumno_id', $alumno->id)->where('anio_id', $periodo->anio_escolar_id)
            ->orderByDesc('fecha_matricula')->orderByDesc('id')->pluck('seccion_id')->unique()->values();

        if ($soloPublicado && $secciones->isNotEmpty()) {
            $cerradas = CierrePeriodo::query()->where('periodo_id', $periodo->id)->whereIn('seccion_id', $secciones)->pluck('seccion_id');
            $secciones = $secciones->filter(fn ($id) => $cerradas->contains($id))->values();
        }

        if ($secciones->isEmpty()) {
            return null;
        }

        $conNotas = DB::table('notas')->join('criterios_evaluacion', 'criterios_evaluacion.id', '=', 'notas.criterio_id')
            ->where('notas.alumno_id', $alumno->id)->where('criterios_evaluacion.periodo_id', $periodo->id)
            ->whereIn('criterios_evaluacion.seccion_id', $secciones)->distinct()->pluck('criterios_evaluacion.seccion_id');

        $id = $secciones->first(fn ($s) => $conNotas->contains($s)) ?? $secciones->first();

        return Seccion::query()->with(['grado.nivel', 'anio'])->find($id);
    }

    /**
     * Notas del alumno en un periodo, por curso. Con `$para` (vista de la familia) se aplican las reglas de
     * pertenencia de criterios y notas; sin él (boleta, que autoriza por su cuenta) se leen directamente.
     *
     * @return array{seccion: Seccion, periodo: PeriodoEvaluacion, cursos: list<array<string, mixed>>, promedio: float|null, etiqueta: string|null}|null
     */
    public function delAlumno(Alumno $alumno, PeriodoEvaluacion $periodo, ?Usuario $para = null, ?Seccion $seccion = null): ?array
    {
        $seccion ??= $this->seccionDelPeriodo($alumno, $periodo, soloPublicado: $para !== null);

        if (! $seccion) {
            return null;
        }

        $calculo = $this->evaluacion->calculo();

        $criterios = CriterioEvaluacion::query()->where('seccion_id', $seccion->id)->where('periodo_id', $periodo->id)
            ->when($para, fn ($q) => $q->visiblePara($para))->orderBy('orden')->orderBy('id')->get();

        $notas = Nota::query()->where('alumno_id', $alumno->id)->whereIn('criterio_id', $criterios->pluck('id'))
            ->when($para, fn ($q) => $q->visiblePara($para))->get()->keyBy('criterio_id');

        $plan = DB::table('curso_grado')->select('curso_id')->where('grado_id', $seccion->grado_id);
        $cursos = Curso::query()->where(fn ($q) => $q->whereIn('cursos.id', $plan)->orWhereIn('cursos.id', $criterios->pluck('curso_id')))
            ->orderBy('nombre')->orderBy('cursos.id')->limit(50)->get();

        $filas = $cursos->map(function (Curso $curso) use ($criterios, $notas, $calculo) {
            $detalle = $criterios->where('curso_id', $curso->id)->map(fn (CriterioEvaluacion $c) => [
                'criterio' => $c,
                'nota' => $notas->get($c->id),
                'etiqueta' => ($n = $notas->get($c->id)) ? $calculo->etiqueta((float) $n->valor_numerico) : null,
            ])->values();

            $promedio = $calculo->promedioPonderado($detalle->map(fn ($d) => [
                'peso' => $d['criterio']->peso, 'nota' => $d['nota']?->valor_numerico,
            ]));

            return [
                'curso' => $curso, 'criterios' => $detalle, 'promedio' => $promedio,
                'etiqueta' => $calculo->etiqueta($promedio), 'aprobado' => $calculo->aprueba($promedio),
            ];
        })->all();

        $general = $calculo->promedioSimple(array_column($filas, 'promedio'));

        return ['seccion' => $seccion, 'periodo' => $periodo, 'cursos' => $filas, 'promedio' => $general, 'etiqueta' => $calculo->etiqueta($general)];
    }

    /**
     * Promedio anual por curso: promedio simple de los promedios de los periodos dados que tienen notas (FR-007).
     * Los periodos sin notas en un curso no cuentan como cero.
     *
     * @param  iterable<PeriodoEvaluacion>  $periodos
     * @return array{cursos: list<array<string, mixed>>, promedio: float|null, etiqueta: string|null, periodos: list<int>}
     */
    public function anualDelAlumno(Alumno $alumno, iterable $periodos, ?Usuario $para = null, bool $soloPublicados = true): array
    {
        $calculo = $this->evaluacion->calculo();
        $porCurso = [];
        $usados = [];

        foreach ($periodos as $periodo) {
            $datos = $this->delAlumno($alumno, $periodo, $para, $this->seccionDelPeriodo($alumno, $periodo, $soloPublicados));

            if (! $datos) {
                continue;
            }

            $usados[] = $periodo->id;

            foreach ($datos['cursos'] as $fila) {
                $porCurso[$fila['curso']->id]['curso'] = $fila['curso'];
                $porCurso[$fila['curso']->id]['promedios'][$periodo->id] = $fila['promedio'];
            }
        }

        $cursos = collect($porCurso)->sortBy(fn ($f) => $f['curso']->nombre)->map(function ($f) use ($calculo) {
            $promedio = $calculo->promedioSimple($f['promedios']);

            return $f + ['promedio' => $promedio, 'etiqueta' => $calculo->etiqueta($promedio), 'aprobado' => $calculo->aprueba($promedio)];
        })->values()->all();

        $general = $calculo->promedioSimple(array_column($cursos, 'promedio'));

        return ['cursos' => $cursos, 'promedio' => $general, 'etiqueta' => $calculo->etiqueta($general), 'periodos' => $usados];
    }
}

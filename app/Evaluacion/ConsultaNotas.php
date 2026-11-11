<?php

namespace App\Evaluacion;

use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use Illuminate\Support\Collection;

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
}

<?php

namespace App\Asistencia;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Seccion;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resúmenes de asistencia con agregados SQL (research R-08). El porcentaje es
 * `cuenta_asistencia / (cuenta_asistencia + cuenta_falta)`: las faltas justificadas y los días sin registro no
 * entran en el denominador; con denominador 0 no hay dato (`null`), nunca un 0 % engañoso.
 */
class ResumenAsistencia
{
    /**
     * Resumen de un alumno en un rango, opcionalmente limitado a una sección.
     *
     * @return array{por_estado: Collection<int, object>, asistencias: int, faltas: int, registrados: int, porcentaje: float|null}
     */
    public function delAlumno(Alumno $alumno, CarbonInterface $desde, CarbonInterface $hasta, ?Seccion $seccion = null): array
    {
        return $this->resumir(
            Asistencia::query()->where('asistencias.alumno_id', $alumno->id)
                ->when($seccion, fn (Builder $q) => $q->where('asistencias.seccion_id', $seccion->id)),
            $desde, $hasta,
        );
    }

    /** @return array{por_estado: Collection<int, object>, asistencias: int, faltas: int, registrados: int, porcentaje: float|null} */
    public function deSeccion(Seccion $seccion, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return $this->resumir(Asistencia::query()->where('asistencias.seccion_id', $seccion->id), $desde, $hasta);
    }

    /**
     * Registros de un alumno en un mes (a lo más 31 filas: no necesita paginación).
     *
     * @return Collection<int, Asistencia>
     */
    public function porMes(Alumno $alumno, int $anio, int $mes): Collection
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        return Asistencia::query()->with('estado')
            ->where('alumno_id', $alumno->id)->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')->get();
    }

    /**
     * Alumnos de la sección ordenados por faltas injustificadas (`cuenta_falta`), de mayor a menor.
     *
     * @return LengthAwarePaginator<int, object>
     */
    public function rankingFaltas(Seccion $seccion, CarbonInterface $desde, CarbonInterface $hasta, int $porPagina = 15): LengthAwarePaginator
    {
        return DB::table('asistencias')
            ->join('estados_asistencia', 'estados_asistencia.id', '=', 'asistencias.estado_id')
            ->join('alumnos', 'alumnos.id', '=', 'asistencias.alumno_id')
            ->where('asistencias.seccion_id', $seccion->id)
            ->whereBetween('asistencias.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('alumnos.id', 'alumnos.apellidos', 'alumnos.nombres')
            ->selectRaw('alumnos.id as alumno_id, alumnos.apellidos, alumnos.nombres,
                SUM(estados_asistencia.cuenta_falta) as faltas,
                SUM(estados_asistencia.cuenta_asistencia) as asistencias,
                SUM(CASE WHEN estados_asistencia.cuenta_falta = 0 AND estados_asistencia.cuenta_asistencia = 0 THEN 1 ELSE 0 END) as justificadas')
            ->orderByDesc('faltas')->orderBy('alumnos.apellidos')->orderBy('alumnos.nombres')->orderBy('alumnos.id')
            ->paginate($porPagina);
    }

    /** Porcentaje de asistencia de una fila del ranking (o de cualquier par asistencias/faltas). */
    public static function porcentaje(int $asistencias, int $faltas): ?float
    {
        $base = $asistencias + $faltas;

        return $base === 0 ? null : round($asistencias * 100 / $base, 1);
    }

    /** @return array{por_estado: Collection<int, object>, asistencias: int, faltas: int, registrados: int, porcentaje: float|null} */
    private function resumir(Builder $base, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $porEstado = $base->join('estados_asistencia', 'estados_asistencia.id', '=', 'asistencias.estado_id')
            ->whereBetween('asistencias.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('estados_asistencia.id', 'estados_asistencia.codigo', 'estados_asistencia.nombre', 'estados_asistencia.orden', 'estados_asistencia.cuenta_asistencia', 'estados_asistencia.cuenta_falta')
            ->orderBy('estados_asistencia.orden')
            ->selectRaw('estados_asistencia.codigo, estados_asistencia.nombre, estados_asistencia.cuenta_asistencia, estados_asistencia.cuenta_falta, COUNT(*) as total')
            ->toBase()->get();

        $asistencias = (int) $porEstado->where('cuenta_asistencia', 1)->sum('total');
        $faltas = (int) $porEstado->where('cuenta_falta', 1)->sum('total');

        return [
            'por_estado' => $porEstado,
            'asistencias' => $asistencias,
            'faltas' => $faltas,
            'registrados' => (int) $porEstado->sum('total'),
            'porcentaje' => self::porcentaje($asistencias, $faltas),
        ];
    }
}

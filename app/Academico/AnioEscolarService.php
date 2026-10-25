<?php

namespace App\Academico;

use App\Models\AnioEscolar;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reglas del año escolar que no se pueden dejar a la interfaz: un solo año vigente (research R-01),
 * periodos sin superposición y dentro del año (R-02). Todo va en transacciones con `lockForUpdate`
 * (hasta 3 intentos ante deadlock), igual que ProteccionAdministracion del Módulo 1.
 */
class AnioEscolarService
{
    /** Marca el año como vigente y deja de serlo al anterior, en una sola operación. */
    public function marcarVigente(AnioEscolar $anio): void
    {
        DB::transaction(function () use ($anio) {
            // Bloquea el vigente actual y el elegido, ordenados por id, antes de decidir nada.
            $filas = AnioEscolar::query()
                ->where('vigente', 1)
                ->orWhere('id', $anio->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $elegido = $filas->get($anio->id) ?? throw new ReglaAcademicaIncumplida('El año escolar ya no existe.');

            if (! $elegido->activo) {
                throw new ReglaAcademicaIncumplida('No se puede marcar como vigente un año desactivado; reactívalo antes.');
            }

            AnioEscolar::query()->where('vigente', 1)->update(['vigente' => null]);
            AnioEscolar::query()->whereKey($elegido->id)->update(['vigente' => 1]);
        }, 3);

        $anio->refresh();
    }

    /** Desactiva un año; el vigente no se desactiva. Bloquea la fila para no competir con marcarVigente(). */
    public function desactivar(AnioEscolar $anio): void
    {
        DB::transaction(function () use ($anio) {
            $fila = AnioEscolar::query()->whereKey($anio->id)->lockForUpdate()->firstOrFail();

            if ($fila->esVigente()) {
                throw new ReglaAcademicaIncumplida('No se puede desactivar el año escolar vigente; marca otro año como vigente antes.');
            }

            $fila->update(['activo' => false]);
        }, 3);
    }

    /** Elimina un año solo si no es el vigente y no tiene secciones ni otros dependientes. */
    public function eliminar(AnioEscolar $anio): void
    {
        DB::transaction(function () use ($anio) {
            $fila = AnioEscolar::query()->whereKey($anio->id)->lockForUpdate()->firstOrFail();

            if ($fila->esVigente()) {
                throw new ReglaAcademicaIncumplida('No se puede eliminar el año escolar vigente.');
            }

            if ($fila->tieneDependientes()) {
                throw new ReglaAcademicaIncumplida('No se puede eliminar un año que tiene secciones; desactívalo para conservar el historial.');
            }

            $fila->delete();
        }, 3);
    }

    /**
     * Crea o edita un año con sus periodos en una sola transacción.
     *
     * @param  array{nombre: string, fecha_inicio: mixed, fecha_fin: mixed, tipo_periodo: TipoPeriodo|string}  $datos
     * @param  array<int, array{nombre: string, fecha_inicio: mixed, fecha_fin: mixed}>|null  $periodos
     *                                                                                                   en orden; null conserva los existentes (y los valida contra las nuevas fechas del año)
     */
    public function guardar(?AnioEscolar $anio, array $datos, ?array $periodos = null): AnioEscolar
    {
        $tipo = $datos['tipo_periodo'] instanceof TipoPeriodo ? $datos['tipo_periodo'] : TipoPeriodo::from($datos['tipo_periodo']);
        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($datos['fecha_fin'])->startOfDay();
        $nombre = preg_replace('/\s+/u', ' ', trim($datos['nombre']));

        if (! $inicio->lt($fin)) {
            throw new ReglaAcademicaIncumplida('La fecha de inicio del año debe ser anterior a la de fin.');
        }

        return DB::transaction(function () use ($anio, $tipo, $inicio, $fin, $nombre, $periodos) {
            $existe = $anio?->exists === true;

            if ($existe) {
                $anio = AnioEscolar::query()->whereKey($anio->id)->lockForUpdate()->firstOrFail();
            }

            $repetido = AnioEscolar::query()->where('nombre', $nombre)->when($existe, fn ($q) => $q->where('id', '!=', $anio->id))->exists();
            if ($repetido) {
                throw new ReglaAcademicaIncumplida('Ya existe un año escolar con ese nombre.');
            }

            $cambiaTipo = $existe && $anio->tipo_periodo !== $tipo;

            if ($cambiaTipo && $this->periodosTienenDependientes($anio)) {
                throw new ReglaAcademicaIncumplida('No se puede cambiar el tipo de periodo: hay registros que dependen de los periodos actuales.');
            }

            if (! $existe && $periodos === null) {
                throw new ReglaAcademicaIncumplida('Un año nuevo debe definir sus periodos.');
            }

            if ($cambiaTipo && $periodos === null) {
                throw new ReglaAcademicaIncumplida('Al cambiar el tipo de periodo hay que definir los nuevos periodos.');
            }

            $aValidar = $periodos ?? $anio->periodos()->get()->map(fn ($p) => [
                'nombre' => $p->nombre, 'fecha_inicio' => $p->fecha_inicio, 'fecha_fin' => $p->fecha_fin,
            ])->all();

            $this->validarPeriodos($tipo, $inicio, $fin, $aValidar);

            $datosAnio = ['nombre' => $nombre, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'tipo_periodo' => $tipo];
            if ($existe) {
                $anio->update($datosAnio);
            } else {
                $anio = AnioEscolar::create($datosAnio + ['activo' => true]);
            }

            if ($periodos !== null) {
                $this->guardarPeriodos($anio, $periodos);
            }

            return $anio;
        }, 3);
    }

    /**
     * Valida el conjunto completo de periodos de un año (no uno a uno): cantidad según el tipo, fechas
     * dentro del año, inicio anterior al fin y orden cronológico estricto sin superposición, de modo
     * que cada día pertenezca a un solo periodo. Pueden quedar días libres entre periodos.
     *
     * @param  array<int, array{nombre: string, fecha_inicio: mixed, fecha_fin: mixed}>  $periodos
     */
    public function validarPeriodos(TipoPeriodo $tipo, CarbonInterface $inicioAnio, CarbonInterface $finAnio, array $periodos): void
    {
        $periodos = array_values($periodos);

        if (count($periodos) !== $tipo->cantidadPeriodos()) {
            throw new ReglaAcademicaIncumplida(sprintf(
                'Un año con %ss debe tener exactamente %d periodos (se recibieron %d).',
                $tipo->value, $tipo->cantidadPeriodos(), count($periodos),
            ));
        }

        $anterior = null;

        foreach ($periodos as $periodo) {
            $nombre = trim((string) ($periodo['nombre'] ?? ''));
            if ($nombre === '' || empty($periodo['fecha_inicio']) || empty($periodo['fecha_fin'])) {
                throw new ReglaAcademicaIncumplida('Todos los periodos necesitan nombre, fecha de inicio y fecha de fin.');
            }

            $desde = Carbon::parse($periodo['fecha_inicio'])->startOfDay();
            $hasta = Carbon::parse($periodo['fecha_fin'])->startOfDay();

            if (! $desde->lt($hasta)) {
                throw new ReglaAcademicaIncumplida("El periodo «{$nombre}» empieza después de que termina (o el mismo día).");
            }

            if ($desde->lt($inicioAnio) || $hasta->gt($finAnio)) {
                throw new ReglaAcademicaIncumplida("El periodo «{$nombre}» cae total o parcialmente fuera del año escolar.");
            }

            if ($anterior !== null && ! $desde->gt($anterior['hasta'])) {
                throw new ReglaAcademicaIncumplida(
                    "El periodo «{$nombre}» se superpone con «{$anterior['nombre']}» o no está en orden cronológico: cada día pertenece a un solo periodo.",
                );
            }

            $anterior = ['nombre' => $nombre, 'hasta' => $hasta];
        }
    }

    /**
     * Guarda los periodos por orden (actualiza en vez de borrar y recrear, para conservar sus ids cuando
     * otros módulos dependan de ellos) y elimina los que sobren si el tipo cambió. La validación previa
     * es responsabilidad de quien llama; `guardar()` la hace.
     *
     * @param  array<int, array{nombre: string, fecha_inicio: mixed, fecha_fin: mixed}>  $periodos
     */
    private function guardarPeriodos(AnioEscolar $anio, array $periodos): void
    {
        $periodos = array_values($periodos);

        foreach ($periodos as $i => $periodo) {
            $anio->periodos()->updateOrCreate(['orden' => $i + 1], [
                'nombre' => preg_replace('/\s+/u', ' ', trim($periodo['nombre'])),
                'fecha_inicio' => Carbon::parse($periodo['fecha_inicio'])->startOfDay(),
                'fecha_fin' => Carbon::parse($periodo['fecha_fin'])->startOfDay(),
            ]);
        }

        $anio->periodos()->where('orden', '>', count($periodos))->delete();
    }

    /**
     * Si otros registros dependen de los periodos del año. Hoy ninguno; los módulos de Asistencia y
     * Evaluación lo extenderán para impedir el cambio de tipo (spec, edge cases).
     */
    protected function periodosTienenDependientes(AnioEscolar $anio): bool
    {
        return false;
    }
}

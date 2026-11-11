<?php

namespace App\Evaluacion;

use App\Models\Alumno;
use App\Models\AsignacionDocente;
use App\Models\CierrePeriodo;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\EscalaCalificacion;
use App\Models\Institucion;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoEvaluacion;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reglas de escritura y datos base de la evaluación (research R-05, R-08, R-09). Los mensajes no llevan datos
 * personales: se identifica por posición (fila/columna) o #id (R-17).
 */
class EvaluacionService
{
    /** Escala de calificación configurada en la institución. */
    public function escala(): EscalaCalificacion
    {
        return Institucion::actual()->escala
            ?? throw new EvaluacionIncumplida('La institución no tiene una escala de calificación configurada.');
    }

    public function calculo(): CalculoPromedios
    {
        return CalculoPromedios::deEscala($this->escala());
    }

    public function exigirAnioVigente(Seccion $seccion): void
    {
        $seccion->loadMissing('anio');

        if ($seccion->anio->vigente === null) {
            throw new EvaluacionIncumplida('Solo se pueden modificar las notas del año escolar vigente.');
        }
    }

    /** El periodo debe pertenecer al año de la sección. */
    public function exigirPeriodoDelAnio(Seccion $seccion, PeriodoEvaluacion $periodo): void
    {
        if ($periodo->anio_escolar_id !== $seccion->anio_escolar_id) {
            throw new EvaluacionIncumplida('El periodo no pertenece al año escolar de la sección.');
        }
    }

    /** Fila presente en `cierres_periodo` = cerrado y publicado (R-05). */
    public function periodoCerrado(Seccion $seccion, PeriodoEvaluacion $periodo): bool
    {
        return CierrePeriodo::query()->where('seccion_id', $seccion->id)->where('periodo_id', $periodo->id)->exists();
    }

    /**
     * Escribe quien gestiona, o quien tiene `notas.registrar_propia`, conserva el marcador de docente y tiene una
     * asignación en el par (sección, curso) (R-08, R-06 del Módulo 2).
     */
    public function puedeEscribir(Usuario $usuario, Seccion $seccion, Curso $curso): bool
    {
        if ($usuario->tienePermiso('notas.gestionar')) {
            return true;
        }

        return $usuario->tienePermiso('notas.registrar_propia')
            && $usuario->tienePermiso(AsignacionDocente::PERMISO_DOCENTE)
            && AsignacionDocente::query()->where('usuario_id', $usuario->id)
                ->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->exists();
    }

    /** Abre la pantalla de notas quien puede escribir o ver todo, o el docente con asignación en el par. */
    public function puedeAbrir(Usuario $usuario, Seccion $seccion, Curso $curso): bool
    {
        return $usuario->tienePermiso('notas.ver_todas')
            || $this->puedeEscribir($usuario, $seccion, $curso)
            || ($usuario->tienePermiso('notas.registrar_propia')
                && AsignacionDocente::query()->where('usuario_id', $usuario->id)
                    ->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->exists());
    }

    /**
     * Comprueba todo lo necesario para escribir: permiso y asignación (403), año vigente, periodo del año y, para
     * quien no gestiona, periodo abierto.
     */
    public function exigirEscritura(Usuario $usuario, Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo): void
    {
        if (! $this->puedeEscribir($usuario, $seccion, $curso)) {
            throw new AccessDeniedHttpException('No puedes registrar las notas de este curso y sección.');
        }

        $this->exigirAnioVigente($seccion);
        $this->exigirPeriodoDelAnio($seccion, $periodo);

        if (! $usuario->tienePermiso('notas.gestionar') && $this->periodoCerrado($seccion, $periodo)) {
            throw new EvaluacionIncumplida('El periodo está cerrado: solo el Administrador puede corregir las notas.');
        }
    }

    /**
     * Alumnos con matrícula activa en la sección, por apellidos, nombres e id (R-09).
     *
     * @return Collection<int, Alumno>
     */
    public function nominaActiva(Seccion $seccion): Collection
    {
        $activos = Matricula::query()->select('alumno_id')->where('seccion_id', $seccion->id)->where('estado', 'matriculado');

        return Alumno::query()->whereIn('alumnos.id', $activos)
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('alumnos.id')->get();
    }

    /**
     * Criterios de un (sección, curso, periodo) en su orden.
     *
     * @return Collection<int, CriterioEvaluacion>
     */
    public function criteriosDelPar(Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo): Collection
    {
        return CriterioEvaluacion::query()
            ->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->where('periodo_id', $periodo->id)
            ->orderBy('orden')->orderBy('id')->get();
    }

    /**
     * Guarda el esquema completo de criterios de un (sección, curso, periodo), todo o nada (R-01, R-11):
     * crea, actualiza y elimina por id; eliminar un criterio borra sus notas. Pesos > 0 y ≤ 100 que suman 100,00.
     *
     * @param  list<array{id?: int|string|null, nombre?: string|null, peso?: int|float|string|null}>  $criterios
     * @return Collection<int, CriterioEvaluacion>
     */
    public function guardarEsquema(Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo, array $criterios, Usuario $usuario): Collection
    {
        $this->exigirEscritura($usuario, $seccion, $curso, $periodo);

        $filas = [];
        $nombres = [];
        $errores = [];

        foreach (array_values($criterios) as $i => $criterio) {
            $n = $i + 1;
            $nombre = trim((string) ($criterio['nombre'] ?? ''));
            $peso = str_replace(',', '.', trim((string) ($criterio['peso'] ?? '')));

            if ($nombre === '' || mb_strlen($nombre) > 60) {
                $errores[] = "Criterio {$n}: el nombre es obligatorio y admite hasta 60 caracteres.";
            } elseif (isset($nombres[mb_strtolower($nombre)])) {
                $errores[] = "Criterio {$n}: el nombre está repetido.";
            }
            $nombres[mb_strtolower($nombre)] = true;

            if (! is_numeric($peso) || preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $peso) !== 1) {
                $errores[] = "Criterio {$n}: el peso debe ser un número con hasta 2 decimales.";
                $peso = null;
            }

            $filas[] = ['id' => ($criterio['id'] ?? null) ? (int) $criterio['id'] : null, 'nombre' => $nombre, 'peso' => $peso];
        }

        if ($filas === []) {
            throw new EvaluacionIncumplida('Define al menos un criterio de evaluación.');
        }

        if ($errores === [] && ! CalculoPromedios::pesosValidos(array_column($filas, 'peso'))) {
            $errores[] = 'Los pesos deben ser mayores que 0 y sumar exactamente 100 %.';
        }

        if ($errores !== []) {
            throw new EvaluacionIncumplida($errores[0], $errores);
        }

        return DB::transaction(function () use ($seccion, $curso, $periodo, $filas) {
            $existentes = CriterioEvaluacion::query()
                ->where('seccion_id', $seccion->id)->where('curso_id', $curso->id)->where('periodo_id', $periodo->id)
                ->lockForUpdate()->get()->keyBy('id');

            $conservados = collect($filas)->pluck('id')->filter();

            if ($conservados->diff($existentes->keys())->isNotEmpty()) {
                throw new EvaluacionIncumplida('Un criterio no pertenece a este curso, sección y periodo.');
            }

            // Eliminar un criterio borra sus notas (cascade); los que no vienen en el esquema se eliminan.
            CriterioEvaluacion::query()->whereIn('id', $existentes->keys()->diff($conservados)->all())->delete();

            // Se liberan los nombres antes de reasignarlos (renombrar A↔B choca con el índice único).
            foreach ($conservados as $id) {
                CriterioEvaluacion::query()->whereKey($id)->update(['nombre' => '~'.$id]);
            }

            foreach ($filas as $orden => $fila) {
                $datos = ['nombre' => $fila['nombre'], 'peso' => $fila['peso'], 'orden' => $orden + 1];

                if ($fila['id']) {
                    CriterioEvaluacion::query()->whereKey($fila['id'])->update($datos);
                } else {
                    CriterioEvaluacion::query()->create($datos + [
                        'seccion_id' => $seccion->id, 'curso_id' => $curso->id, 'periodo_id' => $periodo->id,
                    ]);
                }
            }

            return $this->criteriosDelPar($seccion, $curso, $periodo);
        });
    }

    /**
     * Guarda la tabla de notas de un (sección, curso, periodo), todo o nada (R-10, FR-004). Una celda vacía es
     * «sin nota» y borra la fila; una llena la crea o la reemplaza (`upsert` sobre el índice único).
     *
     * @param  array<int|string, array<int|string, string|null>>  $celdas  `[alumno_id][criterio_id] => valor`
     * @return array{guardadas: int, borradas: int}
     */
    public function guardarNotas(Seccion $seccion, Curso $curso, PeriodoEvaluacion $periodo, array $celdas, Usuario $usuario): array
    {
        $this->exigirEscritura($usuario, $seccion, $curso, $periodo);

        $criterios = $this->criteriosDelPar($seccion, $curso, $periodo);

        if ($criterios->isEmpty()) {
            throw new EvaluacionIncumplida('Define primero los criterios de evaluación de este curso y periodo.');
        }

        $nomina = $this->nominaActiva($seccion);
        $filaDe = $nomina->pluck('id')->flip()->map(fn ($i) => $i + 1);
        $columnaDe = $criterios->pluck('id')->flip()->map(fn ($i) => $i + 1);
        $calculo = $this->calculo();

        $llenas = [];
        $vacias = [];
        $errores = [];

        foreach ($celdas as $alumnoId => $porCriterio) {
            if (! isset($filaDe[(int) $alumnoId])) {
                $errores[] = 'Se envió un alumno que no pertenece a la nómina de la sección.';

                continue;
            }

            foreach ((array) $porCriterio as $criterioId => $crudo) {
                if (! isset($columnaDe[(int) $criterioId])) {
                    $errores[] = 'Se envió un criterio que no pertenece al esquema de este curso y periodo.';

                    continue;
                }

                $crudo = trim((string) $crudo);

                if ($crudo === '') {
                    $vacias[(int) $criterioId][] = (int) $alumnoId;

                    continue;
                }

                try {
                    $llenas[] = ['alumno_id' => (int) $alumnoId, 'criterio_id' => (int) $criterioId] + $calculo->normalizar($crudo);
                } catch (EvaluacionIncumplida) {
                    $errores[] = "Fila {$filaDe[(int) $alumnoId]}, columna {$columnaDe[(int) $criterioId]}: el valor no pertenece a la escala de calificación.";
                }
            }
        }

        if ($errores !== []) {
            throw new EvaluacionIncumplida('No se guardó ninguna nota: '.$errores[0], array_values(array_unique($errores)));
        }

        $ahora = now();

        DB::transaction(function () use ($llenas, $vacias, $usuario, $ahora) {
            foreach (array_chunk($llenas, 500) as $lote) {
                Nota::query()->upsert(
                    array_map(fn (array $c) => [
                        'criterio_id' => $c['criterio_id'], 'alumno_id' => $c['alumno_id'],
                        'valor' => $c['valor'], 'valor_numerico' => $c['numerico'],
                        'registrado_por' => $usuario->id, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ], $lote),
                    ['criterio_id', 'alumno_id'],
                    ['valor', 'valor_numerico', 'registrado_por', 'updated_at'],
                );
            }

            foreach ($vacias as $criterioId => $alumnos) {
                Nota::query()->where('criterio_id', $criterioId)->whereIn('alumno_id', $alumnos)->delete();
            }
        });

        return ['guardadas' => count($llenas), 'borradas' => array_sum(array_map('count', $vacias))];
    }
}

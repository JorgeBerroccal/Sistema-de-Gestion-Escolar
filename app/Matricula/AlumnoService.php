<?php

namespace App\Matricula;

use App\Models\Alumno;
use App\Models\TipoDocumento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Altas, ediciones, desactivación y eliminación de alumnos. Los mensajes de error nunca llevan nombres ni
 * documentos (Ley 29733, FR-026). El índice único `(tipo_documento_id, numero_documento)` es la última defensa
 * del documento único ante operaciones simultáneas.
 */
class AlumnoService
{
    /**
     * @param  array{nombres: string, apellidos: string, tipo_documento_id: int|string, numero_documento: string, fecha_nacimiento: string, sexo: string}  $datos
     */
    public function crear(array $datos): Alumno
    {
        $datos = $this->normalizar($datos);
        $this->validar($datos, null);

        try {
            return Alumno::create($datos + ['activo' => true]);
        } catch (UniqueConstraintViolationException) {
            throw new MatriculaIncumplida('Ya existe un alumno con ese tipo y número de documento.');
        }
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(Alumno $alumno, array $datos): Alumno
    {
        $datos = $this->normalizar($datos);
        $this->validar($datos, $alumno);

        try {
            $alumno->update($datos);
        } catch (UniqueConstraintViolationException) {
            throw new MatriculaIncumplida('Ya existe un alumno con ese tipo y número de documento.');
        }

        return $alumno;
    }

    /** No se desactiva a un alumno con matrícula activa: antes se retira o traslada (FR-005). */
    public function desactivar(Alumno $alumno): void
    {
        DB::transaction(function () use ($alumno) {
            $bloqueado = Alumno::query()->whereKey($alumno->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->matriculas()->activas()->exists()) {
                throw new MatriculaIncumplida('El alumno tiene una matrícula activa: retírala o trasládala antes de desactivarlo.');
            }

            $bloqueado->update(['activo' => false]);
        }, 3);

        $alumno->refresh();
    }

    public function reactivar(Alumno $alumno): void
    {
        $alumno->update(['activo' => true]);
    }

    /** Solo se borra físicamente a un alumno sin matrículas (FR-005). */
    public function eliminar(Alumno $alumno): void
    {
        DB::transaction(function () use ($alumno) {
            $bloqueado = Alumno::query()->whereKey($alumno->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->matriculas()->exists()) {
                throw new MatriculaIncumplida('El alumno tiene matrículas: no se puede eliminar, solo desactivar para conservar el historial.');
            }

            $bloqueado->delete();
        }, 3);
    }

    /** @param  array<string, mixed>  $datos */
    private function normalizar(array $datos): array
    {
        foreach (['nombres', 'apellidos'] as $campo) {
            $datos[$campo] = preg_replace('/\s+/u', ' ', trim((string) ($datos[$campo] ?? '')));
        }

        $datos['numero_documento'] = mb_strtoupper(trim((string) ($datos['numero_documento'] ?? '')));

        return $datos;
    }

    /** @param  array<string, mixed>  $datos */
    private function validar(array $datos, ?Alumno $actual): void
    {
        foreach (['nombres' => 'Los nombres', 'apellidos' => 'Los apellidos'] as $campo => $etiqueta) {
            if ($datos[$campo] === '') {
                throw new MatriculaIncumplida("{$etiqueta} son obligatorios.");
            }

            if (mb_strlen($datos[$campo]) > 100) {
                throw new MatriculaIncumplida("{$etiqueta} admiten como máximo 100 caracteres.");
            }
        }

        $tipo = TipoDocumento::query()->where('activo', true)->find($datos['tipo_documento_id'] ?? null)
            ?? throw new MatriculaIncumplida('Elige un tipo de documento válido.');

        if ($datos['numero_documento'] === '') {
            throw new MatriculaIncumplida('El número de documento es obligatorio.');
        }

        if ($error = $tipo->errorDeFormato($datos['numero_documento'])) {
            throw new MatriculaIncumplida($error);
        }

        if (Sexo::tryFrom((string) ($datos['sexo'] ?? '')) === null) {
            throw new MatriculaIncumplida('Elige el sexo del alumno.');
        }

        $nacimiento = date_create((string) ($datos['fecha_nacimiento'] ?? ''));

        if ($nacimiento === false) {
            throw new MatriculaIncumplida('La fecha de nacimiento no es válida.');
        }

        if ($nacimiento > now()) {
            throw new MatriculaIncumplida('La fecha de nacimiento no puede ser futura.');
        }

        $repetido = Alumno::query()
            ->where('tipo_documento_id', $tipo->id)
            ->where('numero_documento', $datos['numero_documento'])
            ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
            ->exists();

        if ($repetido) {
            throw new MatriculaIncumplida('Ya existe un alumno con ese tipo y número de documento.');
        }
    }
}

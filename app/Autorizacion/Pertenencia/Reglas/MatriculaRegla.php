<?php

namespace App\Autorizacion\Pertenencia\Reglas;

use App\Autorizacion\Pertenencia\ReglaPertenencia;
use App\Models\Matricula;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `matricula.ver_todas` ve todo. Si no, se unen (OR) las matrículas de las secciones donde el usuario tiene una
 * asignación docente (`matricula.ver_nomina_propia`) y las de los alumnos propios o hijos (`matricula.ver_propia`).
 * Sin ninguno de los permisos no se ve nada (fail-closed).
 */
class MatriculaRegla implements ReglaPertenencia
{
    public function aplicar(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->tienePermiso('matricula.ver_todas')) {
            return $query;
        }

        $nomina = $usuario->tienePermiso('matricula.ver_nomina_propia');
        $propia = $usuario->tienePermiso('matricula.ver_propia');

        if (! $nomina && ! $propia) {
            return $query->whereRaw('1 = 0');
        }

        $modelo = $query->getModel();

        return $query->where(function (Builder $q) use ($usuario, $nomina, $propia, $modelo) {
            $q->whereRaw('1 = 0');

            if ($nomina) {
                $q->orWhereIn($modelo->qualifyColumn('seccion_id'), self::seccionesDelDocente($usuario));
            }

            if ($propia) {
                $q->orWhereIn($modelo->qualifyColumn('alumno_id'), self::alumnosPropios($usuario));
            }
        });
    }

    public function permite(Usuario $usuario, Model $registro): bool
    {
        if ($usuario->tienePermiso('matricula.ver_todas')) {
            return true;
        }

        return $registro instanceof Matricula
            && Matricula::query()->whereKey($registro->getKey())->visiblePara($usuario)->exists();
    }

    /** Secciones donde el usuario figura como docente (de cualquier año). */
    public static function seccionesDelDocente(Usuario $usuario): \Illuminate\Database\Query\Builder
    {
        return DB::table('asignaciones_docentes')->select('seccion_id')->where('usuario_id', $usuario->id);
    }

    /** Alumno vinculado al usuario más los alumnos de los que es apoderado. */
    public static function alumnosPropios(Usuario $usuario): \Illuminate\Database\Query\Builder
    {
        return DB::table('alumnos')->select('id')->where('usuario_id', $usuario->id)
            ->union(DB::table('alumno_apoderado')->select('alumno_id')->where('usuario_id', $usuario->id));
    }
}

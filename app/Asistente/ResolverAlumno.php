<?php

namespace App\Asistente;

use App\Autorizacion\Pertenencia\Reglas\MatriculaRegla;
use App\Models\Alumno;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decide a qué alumno propio se refiere la pregunta (research R-06), sin IA y solo entre los alumnos propios o hijos
 * vinculados: un nombre ajeno nunca coincide, así que nunca se confirma ni se consulta a otra persona. Los permisos
 * `*.ver_todas` no amplían esto: el asistente es siempre de lo propio.
 */
class ResolverAlumno
{
    public function resolver(Usuario $usuario, string $mensaje): Resolucion
    {
        $propios = $this->propios($usuario);

        if ($propios->isEmpty()) {
            return Resolucion::sinDatos('No tienes estudiantes vinculados a tu cuenta, así que no tengo información para consultar.');
        }

        if ($propios->count() === 1) {
            return Resolucion::alumno($propios->first());
        }

        $palabras = $this->palabras($mensaje);
        $coinciden = $propios->filter(fn (Alumno $a) => array_intersect($this->palabras($a->nombres), $palabras) !== []);

        if ($coinciden->count() > 1) {
            // Nombres parecidos entre hermanos («Luis» y «Luis Miguel»): gana el más específico que esté completo.
            $completos = $coinciden->filter(fn (Alumno $a) => array_diff($this->palabras($a->nombres), $palabras) === []);
            $mayor = $completos->max(fn (Alumno $a) => count($this->palabras($a->nombres)));
            $coinciden = $completos->filter(fn (Alumno $a) => count($this->palabras($a->nombres)) === $mayor);
        }

        if ($coinciden->count() === 1) {
            return Resolucion::alumno($coinciden->first());
        }

        return Resolucion::aclaracion('¿De cuál de tus estudiantes quieres saber? Escribe su nombre: '
            .$propios->map(fn (Alumno $a) => $a->nombres)->implode(', ').'.');
    }

    /** @return Collection<int, Alumno> solo `id`, `nombres` y `apellidos`: nunca se cargan documento ni fecha de nacimiento */
    private function propios(Usuario $usuario): Collection
    {
        return Alumno::query()->select(['id', 'nombres', 'apellidos'])
            ->whereIn('alumnos.id', MatriculaRegla::alumnosPropios($usuario))
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('id')->limit(50)->get();
    }

    /** @return list<string> palabras de 3 o más letras, sin tildes ni mayúsculas */
    private function palabras(string $texto): array
    {
        $limpio = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($texto)));

        return array_values(array_unique(array_filter(explode(' ', $limpio), fn (string $p) => strlen($p) >= 3)));
    }
}

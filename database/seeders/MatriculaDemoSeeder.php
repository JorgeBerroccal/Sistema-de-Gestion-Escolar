<?php

namespace Database\Seeders;

use App\Matricula\AlumnoService;
use App\Matricula\EstadoMatricula;
use App\Matricula\Parentesco;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Matricula;
use App\Models\Seccion;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos de ejemplo del Módulo 3: alumnos y matrículas FICTICIOS (Ley 29733: son datos personales de menores).
 * Nombres inventados, documentos del rango reservado `9000xxxx` y ninguna cuenta de usuario creada: en staging
 * no hay vínculos (las cuentas de Padre o Alumno de las pruebas las crea el responsable desde el panel);
 * solo si ya existen los usuarios de ejemplo de local (`alumno.demo`, `padre.demo`) se vinculan a las primeras
 * fichas. Idempotente. Rechaza producción. `DatabaseSeeder` lo llama solo en local; en staging se ejecuta a mano
 * con `db:seed --class=MatriculaDemoSeeder` después de `AcademicoDemoSeeder`.
 */
class MatriculaDemoSeeder extends Seeder
{
    public const ENTORNOS_PERMITIDOS = ['local', 'staging', 'testing'];

    /** Prefijo reservado de los documentos ficticios. */
    public const PREFIJO_DOCUMENTO = '9000';

    private const ALUMNOS_POR_SECCION = 8;

    private const SECCIONES = 3;

    private const NOMBRES = ['Luna', 'Sol', 'Río', 'Alba', 'Bruno', 'Cielo', 'Dante', 'Estrella', 'Fénix', 'Gala', 'Hugo', 'Iris'];

    private const APELLIDOS = ['Ficticio Uno', 'Ficticio Dos', 'Ejemplo Tres', 'Ejemplo Cuatro', 'Prueba Cinco', 'Prueba Seis'];

    public function run(): void
    {
        if (! app()->environment(self::ENTORNOS_PERMITIDOS)) {
            throw new RuntimeException(sprintf(
                'MatriculaDemoSeeder solo puede ejecutarse en %s; el entorno actual es "%s". Los alumnos son datos personales de menores.',
                implode(', ', self::ENTORNOS_PERMITIDOS),
                app()->environment(),
            ));
        }

        $this->call([RolesYPermisosSeeder::class, TiposDocumentoSeeder::class]);

        $vigente = AnioEscolar::where('vigente', 1)->first()
            ?? throw new RuntimeException('No hay un año escolar vigente: ejecuta antes AcademicoDemoSeeder.');
        $secciones = Seccion::where('anio_escolar_id', $vigente->id)->where('activo', true)
            ->orderBy('grado_id')->orderBy('identificador')->limit(self::SECCIONES)->get();

        if ($secciones->isEmpty()) {
            throw new RuntimeException('No hay secciones en el año vigente: ejecuta antes AcademicoDemoSeeder.');
        }

        DB::transaction(function () use ($vigente, $secciones) {
            $dni = TipoDocumento::where('codigo', 'DNI')->firstOrFail();
            $numero = 1;

            foreach ($secciones as $seccion) {
                foreach (range(1, self::ALUMNOS_POR_SECCION) as $i) {
                    $alumno = Alumno::firstOrCreate(
                        ['tipo_documento_id' => $dni->id, 'numero_documento' => self::PREFIJO_DOCUMENTO.str_pad((string) $numero, 4, '0', STR_PAD_LEFT)],
                        [
                            'nombres' => self::NOMBRES[($numero - 1) % count(self::NOMBRES)].' Demo '.$numero,
                            'apellidos' => self::APELLIDOS[($numero - 1) % count(self::APELLIDOS)],
                            'fecha_nacimiento' => (now()->year - 10).'-'.str_pad((string) (($numero % 12) + 1), 2, '0', STR_PAD_LEFT).'-15',
                            'sexo' => $numero % 2 === 0 ? 'F' : 'M',
                            'activo' => true,
                        ],
                    );

                    $this->matricular($alumno, $seccion, $vigente, retirada: $numero % 11 === 0);
                    $numero++;
                }
            }

            $this->vincularUsuariosDeEjemplo(Alumno::orderBy('id')->limit(2)->get());
        });
    }

    private function matricular(Alumno $alumno, Seccion $seccion, AnioEscolar $anio, bool $retirada): void
    {
        if (Matricula::where('alumno_id', $alumno->id)->where('anio_id', $anio->id)->exists()) {
            return;
        }

        Matricula::create([
            'alumno_id' => $alumno->id,
            'seccion_id' => $seccion->id,
            'anio_id' => $anio->id,
            'fecha_matricula' => now()->year.'-03-01',
            'estado' => $retirada ? EstadoMatricula::Retirado : EstadoMatricula::Matriculado,
            'fecha_estado' => $retirada ? now()->year.'-04-15' : null,
            'motivo' => $retirada ? 'Retiro de ejemplo' : null,
        ]);
    }

    /** Solo vincula usuarios que YA existen (local); nunca crea cuentas. */
    private function vincularUsuariosDeEjemplo($alumnos): void
    {
        $servicio = app(AlumnoService::class);
        $usuarioAlumno = Usuario::where('username', 'alumno.demo')->first();
        $apoderado = Usuario::where('username', 'padre.demo')->first();

        if ($usuarioAlumno && $alumnos->isNotEmpty() && $alumnos[0]->usuario_id === null
            && ! Alumno::where('usuario_id', $usuarioAlumno->id)->exists()) {
            $servicio->vincularUsuario($alumnos[0], $usuarioAlumno);
        }

        if ($apoderado) {
            foreach ($alumnos as $alumno) {
                if (! $alumno->apoderados()->where('usuarios.id', $apoderado->id)->exists()) {
                    $servicio->agregarApoderado($alumno, $apoderado, Parentesco::Madre);
                }
            }
        }
    }
}

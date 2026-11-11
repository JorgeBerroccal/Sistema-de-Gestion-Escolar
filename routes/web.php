<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BoletaController;
use App\Http\Controllers\NotasController;
use App\Livewire\Academico;
use App\Livewire\Admin\Permisos;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Usuarios;
use App\Livewire\Asistencia;
use App\Livewire\Evaluacion;
use App\Livewire\Matricula;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::redirect('/', '/inicio');
    Route::view('/inicio', 'inicio')->name('inicio');

    Route::livewire('/admin/usuarios', Usuarios\Index::class)->name('admin.usuarios.index')->middleware('can:usuarios.ver');
    Route::livewire('/admin/usuarios/crear', Usuarios\Form::class)->name('admin.usuarios.create')->middleware('can:usuarios.crear');
    Route::livewire('/admin/usuarios/{usuario}/editar', Usuarios\Form::class)->name('admin.usuarios.edit')->middleware('can:usuarios.editar');

    Route::livewire('/admin/permisos', Permisos\Index::class)->name('admin.permisos.index')->middleware('can:roles.ver');
    Route::livewire('/admin/roles', Roles\Index::class)->name('admin.roles.index')->middleware('can:roles.ver');
    Route::livewire('/admin/roles/{rol}/permisos', Roles\Permisos::class)->name('admin.roles.permisos')->middleware('can:roles.ver');

    Route::livewire('/academico/institucion', Academico\Institucion\Edit::class)->name('academico.institucion')->middleware('can:academico.ver');
    Route::livewire('/academico/niveles', Academico\Niveles\Index::class)->name('academico.niveles.index')->middleware('can:academico.ver');
    Route::livewire('/academico/anios', Academico\AniosEscolares\Index::class)->name('academico.anios.index')->middleware('can:academico.ver');
    Route::livewire('/academico/anios/crear', Academico\AniosEscolares\Form::class)->name('academico.anios.create')->middleware('can:academico.gestionar');
    Route::livewire('/academico/anios/{anio}/editar', Academico\AniosEscolares\Form::class)->name('academico.anios.edit')->middleware('can:academico.gestionar');
    Route::livewire('/academico/grados', Academico\Grados\Index::class)->name('academico.grados.index')->middleware('can:academico.ver');
    Route::livewire('/academico/secciones', Academico\Secciones\Index::class)->name('academico.secciones.index')->middleware('can:academico.ver');
    Route::livewire('/academico/cursos', Academico\Cursos\Index::class)->name('academico.cursos.index')->middleware('can:academico.ver');
    Route::livewire('/academico/cursos/crear', Academico\Cursos\Form::class)->name('academico.cursos.create')->middleware('can:academico.gestionar');
    Route::livewire('/academico/cursos/{curso}/editar', Academico\Cursos\Form::class)->name('academico.cursos.edit')->middleware('can:academico.gestionar');
    Route::livewire('/academico/asignaciones', Academico\Asignaciones\Index::class)->name('academico.asignaciones.index')->middleware('can:asignaciones.ver_todas');
    Route::livewire('/academico/asignaciones/crear', Academico\Asignaciones\Form::class)->name('academico.asignaciones.create')->middleware('can:asignaciones.gestionar');
    Route::livewire('/academico/mis-asignaciones', Academico\Asignaciones\Index::class)->name('academico.asignaciones.propias')->middleware('can:asignaciones.ver_propias');
    // `crear` va antes que `{asignacion}` para que no se interprete como un id.
    Route::livewire('/academico/asignaciones/{asignacion}', Academico\Asignaciones\Show::class)->name('academico.asignaciones.show');

    // Módulo 3 — Matrícula. `crear` va antes que `{alumno}` para que no se interprete como un id.
    Route::livewire('/matricula/alumnos', Matricula\Alumnos\Index::class)->name('matricula.alumnos.index')->middleware('can:matricula.ver_todas');
    Route::livewire('/matricula/alumnos/crear', Matricula\Alumnos\Form::class)->name('matricula.alumnos.create')->middleware('can:matricula.gestionar');
    Route::livewire('/matricula/alumnos/{alumno}/editar', Matricula\Alumnos\Form::class)->name('matricula.alumnos.edit')->middleware('can:matricula.gestionar');
    Route::livewire('/matricula/alumnos/{alumno}', Matricula\Alumnos\Show::class)->name('matricula.alumnos.show');
    Route::livewire('/matricula/mis-alumnos', Matricula\MisAlumnos\Index::class)->name('matricula.propios.index');
    Route::livewire('/matricula/nominas', Matricula\Nominas\Index::class)->name('matricula.nominas.index');
    Route::livewire('/matricula/nominas/{seccion}', Matricula\Nominas\Show::class)->name('matricula.nominas.show');

    // Módulo 4 — Asistencia. El permiso (doble) y la pertenencia se comprueban en `mount()`.
    Route::livewire('/asistencia/tomar', Asistencia\Tomar\Index::class)->name('asistencia.tomar.index');
    Route::livewire('/asistencia/tomar/{seccion}', Asistencia\Tomar\Show::class)->name('asistencia.tomar.show');
    Route::livewire('/asistencia/mia', Asistencia\Propia\Index::class)->name('asistencia.propia.index');
    Route::livewire('/asistencia/reportes', Asistencia\Reportes\Index::class)->name('asistencia.reportes.index');
    Route::livewire('/asistencia/reportes/{seccion}/alumnos/{alumno}', Asistencia\Reportes\Alumno::class)->name('asistencia.reportes.alumno');

    // Módulo 5 — Evaluación y Boletas. Cada historia registra aquí sus rutas junto a su componente; el permiso (doble)
    // y la pertenencia se comprueban en `mount()` o en el controlador.
    Route::livewire('/notas/registro', Evaluacion\Registro\Index::class)->name('notas.registro.index');
    Route::livewire('/notas/registro/{seccion}/{curso}', Evaluacion\Registro\Show::class)->name('notas.registro.show');
    Route::post('/notas/registro/{seccion}/{curso}', [NotasController::class, 'guardar'])->name('notas.registro.guardar');
    Route::livewire('/notas/mias', Evaluacion\Propia\Index::class)->name('notas.propia.index');
    Route::livewire('/notas/cierres', Evaluacion\Cierres\Index::class)->name('notas.cierres.index')->middleware('can:notas.cerrar_periodo');
    // La boleta autoriza en el controlador (permiso, vínculo y publicación) antes de generar el PDF.
    Route::get('/boletas/{estudiante}/{periodo}', [BoletaController::class, 'descargar'])->name('boletas.descargar')->whereNumber('estudiante')->where('periodo', '[0-9]+|anual');
});

<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Admin\Permisos;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Usuarios;
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
});

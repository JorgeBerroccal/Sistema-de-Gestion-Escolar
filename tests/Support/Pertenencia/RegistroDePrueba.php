<?php

namespace Tests\Support\Pertenencia;

use App\Autorizacion\Pertenencia\RequierePertenencia;
use Illuminate\Database\Eloquent\Model;

class RegistroDePrueba extends Model
{
    use RequierePertenencia;

    protected $table = 'registros_de_prueba';

    protected $guarded = [];

    public $timestamps = false;
}

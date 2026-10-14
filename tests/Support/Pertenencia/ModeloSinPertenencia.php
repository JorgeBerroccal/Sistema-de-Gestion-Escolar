<?php

namespace Tests\Support\Pertenencia;

use Illuminate\Database\Eloquent\Model;

class ModeloSinPertenencia extends Model
{
    protected $table = 'modelos_sin_pertenencia';

    protected $guarded = [];

    public $timestamps = false;
}

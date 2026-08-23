<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaDocumento extends Model
{
    protected $fillable = [
        'tipo',
        'nombre_documento',
        'orden',
    ];
}

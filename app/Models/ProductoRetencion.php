<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoRetencion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'producto_id',
        'retencion_id',
    ];
}

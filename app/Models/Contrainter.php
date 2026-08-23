<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contrainter extends Model
{
    use HasFactory;

    protected $fillable = [
        'detalle',
        'concargo_a',
        'plazoejecucion',
    ];

    protected $casts = [];
}

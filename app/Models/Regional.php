<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Regional extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'director_id',
        'presupuesto_id',
        'municipio_id',
        'firma_nombre_coord_admin',
        'firma_cargo_admin',
        'firma_nombre_coord_contrato',
        'firma_cargo_contrato',
    ];

    public function director(): BelongsTo
    {
        return $this->belongsTo(Director::class);
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}

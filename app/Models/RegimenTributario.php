<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RegimenTributario extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Retenciones que aplican a este régimen (pivote regimen_retencion).
     * Este es el mapeo editable desde el CRUD de Régimen Tributario.
     */
    public function retenciones(): BelongsToMany
    {
        return $this->belongsToMany(Retencion::class, 'regimen_retencion');
    }
}

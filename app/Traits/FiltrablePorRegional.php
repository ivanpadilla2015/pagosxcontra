<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait FiltrablePorRegional
{
    public static function getUserRegionalId(): ?int
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return null;
        }

        return $user->regional_id ?? false;
    }

    public static function scopePorRegional(Builder $query): Builder
    {
        $regionalId = static::getUserRegionalId();

        if ($regionalId === null) {
            return $query;
        }

        if ($regionalId === false) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('user', fn($q) => $q->where('regional_id', $regionalId));
    }

    public static function scopePorRegionalContrato(Builder $query): Builder
    {
        $regionalId = static::getUserRegionalId();

        if ($regionalId === null) {
            return $query;
        }

        if ($regionalId === false) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('contrato.user', fn($q) => $q->where('regional_id', $regionalId));
    }
}
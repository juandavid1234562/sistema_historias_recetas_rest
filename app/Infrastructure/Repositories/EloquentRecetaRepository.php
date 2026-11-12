<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\RecetaRepositoryInterface;
use App\Models\RecetaMedica;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implementación concreta del repositorio de Recetas Médicas usando Eloquent ORM.
 */
class EloquentRecetaRepository implements RecetaRepositoryInterface
{
    public function crear(array $datos): RecetaMedica
    {
        return RecetaMedica::create($datos);
    }

    public function obtenerPorAtencionId(int $atencionId): Collection
    {
        return RecetaMedica::where('atencion_id', $atencionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

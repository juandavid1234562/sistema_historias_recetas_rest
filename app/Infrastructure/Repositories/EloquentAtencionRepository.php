<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\AtencionRepositoryInterface;
use App\Models\AtencionClinica;
use Illuminate\Database\Eloquent\Collection;

/**
 * Implementación concreta del repositorio de Atenciones usando Eloquent ORM.
 * Pertenece a la capa de Infraestructura de la Arquitectura Limpia.
 */
class EloquentAtencionRepository implements AtencionRepositoryInterface
{
    public function crear(array $datos): AtencionClinica
    {
        return AtencionClinica::create($datos);
    }

    public function obtenerPorIdConRecetas(int $id): ?AtencionClinica
    {
        return AtencionClinica::with('recetas')->find($id);
    }

    public function obtenerPorId(int $id): ?AtencionClinica
    {
        return AtencionClinica::find($id);
    }

    public function listarTodas(): Collection
    {
        return AtencionClinica::with('recetas')->orderBy('fecha_atencion', 'desc')->get();
    }
}

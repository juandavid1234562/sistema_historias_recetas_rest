<?php

namespace App\Domain\Contracts;

use App\Models\AtencionClinica;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de repositorio para AtencionClinica (Patrón Repository - PDF 2).
 * Desacopla la lógica de negocio de la tecnología de persistencia (Eloquent/SQL).
 */
interface AtencionRepositoryInterface
{
    /**
     * Guarda una nueva atención clínica en la persistencia.
     */
    public function crear(array $datos): AtencionClinica;

    /**
     * Busca una atención clínica por su ID, incluyendo sus recetas asociadas.
     */
    public function obtenerPorIdConRecetas(int $id): ?AtencionClinica;

    /**
     * Busca una atención clínica únicamente por su ID básico.
     */
    public function obtenerPorId(int $id): ?AtencionClinica;

    /**
     * Lista todas las atenciones clínicas registradas con sus relaciones.
     *
     * @return Collection<int, AtencionClinica>
     */
    public function listarTodas(): Collection;
}

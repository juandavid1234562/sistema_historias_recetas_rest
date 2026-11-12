<?php

namespace App\Domain\Contracts;

use App\Models\RecetaMedica;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato de repositorio para RecetaMedica (Patrón Repository - PDF 2).
 */
interface RecetaRepositoryInterface
{
    /**
     * Registra y asocia una receta médica.
     */
    public function crear(array $datos): RecetaMedica;

    /**
     * Obtiene las recetas médicas asociadas a una atención clínica específica.
     *
     * @return Collection<int, RecetaMedica>
     */
    public function obtenerPorAtencionId(int $atencionId): Collection;
}

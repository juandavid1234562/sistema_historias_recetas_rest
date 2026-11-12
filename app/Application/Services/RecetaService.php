<?php

namespace App\Application\Services;

use App\Domain\Contracts\AtencionRepositoryInterface;
use App\Domain\Contracts\RecetaRepositoryInterface;
use App\Domain\Exceptions\AtencionNoEncontradaException;
use App\Models\RecetaMedica;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio de Aplicación para Recetas Médicas (Capa de Aplicación - Arquitectura Limpia).
 * Aplica Inyección de Dependencias por Constructor (PDF 2 - Sección 2.4).
 */
class RecetaService
{
    public function __construct(
        private readonly RecetaRepositoryInterface $recetaRepository,
        private readonly AtencionRepositoryInterface $atencionRepository
    ) {}

    /**
     * Crea y asocia una receta médica a una atención clínica existente.
     *
     * @param array $datos
     * @return RecetaMedica
     * @throws AtencionNoEncontradaException
     */
    public function crearReceta(array $datos): RecetaMedica
    {
        $atencionId = (int) $datos['atencion_id'];

        // Validar que la atención exista previamente
        $atencion = $this->atencionRepository->obtenerPorId($atencionId);
        if (!$atencion) {
            throw new AtencionNoEncontradaException(
                "No es posible crear la receta. La atención clínica con ID {$atencionId} no existe.",
                404
            );
        }

        return $this->recetaRepository->crear([
            'atencion_id' => $atencionId,
            'medicamento' => $datos['medicamento'],
            'dosis' => $datos['dosis'],
            'indicaciones' => $datos['indicaciones'],
        ]);
    }

    /**
     * Obtiene todas las recetas asociadas a una atención clínica específica.
     *
     * @param int $atencionId
     * @return Collection<int, RecetaMedica>
     * @throws AtencionNoEncontradaException
     */
    public function obtenerRecetasPorAtencion(int $atencionId): Collection
    {
        $atencion = $this->atencionRepository->obtenerPorId($atencionId);
        if (!$atencion) {
            throw new AtencionNoEncontradaException(
                "No se encontró la atención clínica con ID {$atencionId} para consultar sus recetas.",
                404
            );
        }

        return $this->recetaRepository->obtenerPorAtencionId($atencionId);
    }
}

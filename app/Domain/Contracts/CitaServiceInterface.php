<?php

namespace App\Domain\Contracts;

use App\Domain\Exceptions\CitaInvalidaException;

/**
 * Contrato para el servicio de verificación de citas.
 * Según los principios de Arquitectura Limpia e Inversión de Dependencias (DIP),
 * el dominio define la interfaz que el negocio necesita, abstrayendo el protocolo
 * subyacente (SOAP, REST, etc.).
 */
interface CitaServiceInterface
{
    /**
     * Consulta una cita por su ID desde el servicio SOAP.
     *
     * @param int $citaId
     * @return array|null Datos de la cita o null si no existe
     */
    public function consultarCitaPorId(int $citaId): ?array;

    /**
     * Valida si una cita existe y se encuentra en estado activo ('Programada').
     * Lanza CitaInvalidaException si la cita no es válida para registrar atención.
     *
     * @param int $citaId
     * @return array Datos de la cita validada
     * @throws CitaInvalidaException
     */
    public function validarCitaActiva(int $citaId): array;
}

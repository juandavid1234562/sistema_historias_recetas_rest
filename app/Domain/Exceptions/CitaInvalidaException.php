<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando una cita no existe en el servicio SOAP
 * o no se encuentra en estado activo/válido.
 */
class CitaInvalidaException extends Exception
{
    protected int $citaId;

    public function __construct(string $message, int $citaId = 0, int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->citaId = $citaId;
    }

    public function getCitaId(): int
    {
        return $this->citaId;
    }
}

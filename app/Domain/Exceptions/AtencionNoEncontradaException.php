<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando una atención clínica solicitada no existe.
 */
class AtencionNoEncontradaException extends Exception
{
    public function __construct(string $message = 'La atención clínica especificada no existe.', int $code = 404, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

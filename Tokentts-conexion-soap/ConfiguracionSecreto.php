<?php

namespace TokenttsConexionSoap;

/**
 * Configuración de la Clave Secreta Compartida (Shared Secret Key)
 * Utilizada para la generación en REST y validación en SOAP.
 */
class ConfiguracionSecreto
{
    // Clave secreta simétrica compartida entre REST y SOAP
    public const CLAVE_SECRETA = 'Consultorio_Secret_Key_Corte2_3_2026!#';

    // Duración estándar de vigencia del token (en minutos)
    public const MINUTOS_VIGENCIA = 30;

    public static function obtenerClave(): string
    {
        $envKey = function_exists('env') ? env('SOAP_SECRET_KEY') : false;
        if ($envKey === null || $envKey === false) {
            $envKey = getenv('SOAP_SECRET_KEY');
        }
        if ($envKey !== null && $envKey !== false && trim((string)$envKey) !== '') {
            return trim((string)$envKey);
        }
        return self::CLAVE_SECRETA;
    }
}

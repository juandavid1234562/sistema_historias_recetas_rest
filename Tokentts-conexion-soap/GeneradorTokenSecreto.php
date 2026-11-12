<?php

namespace TokenttsConexionSoap;

require_once __DIR__ . '/ConfiguracionSecreto.php';

/**
 * Generador de Tokens Criptográficos con Clave Secreta Compartida (HMAC-SHA256).
 *
 * Implementa el Enfoque 2 (Token firmado simétricamente / Stateless):
 * Genera un token desde la aplicación REST utilizando una clave secreta que
 * únicamente conocen la API REST y el servicio SOAP.
 *
 * Estructura del Token:
 *   [PAYLOAD_BASE64_URL] . [FIRMA_HMAC_SHA256]
 */
class GeneradorTokenSecreto
{
    private string $claveSecreta;

    public function __construct(?string $claveSecreta = null)
    {
        $this->claveSecreta = $claveSecreta ?? ConfiguracionSecreto::obtenerClave();
    }

    /**
     * Genera un nuevo token criptográfico firmado con la clave secreta compartida.
     *
     * @param string $usuario Identificador o email del usuario/sistema emisor
     * @param int $minutosVigencia Duración del token en minutos
     * @param array $datosAdicionales Claims opcionales (roles, permisos, etc.)
     * @return string Token firmado en formato Base64Url.Firma
     */
    public function generar(string $usuario = 'api_rest_laravel', int $minutosVigencia = 30, array $datosAdicionales = []): string
    {
        return self::generarConClave($usuario, $minutosVigencia, $this->claveSecreta, $datosAdicionales);
    }

    /**
     * Método estático para generar tokens permitiendo especificar la clave secreta
     * (útil para pruebas de generación con clave correcta e incorrecta).
     */
    public static function generarConClave(string $usuario, int $minutosVigencia, string $clave, array $datosAdicionales = []): string
    {
        $ahora = time();
        $expiracion = $ahora + ($minutosVigencia * 60);

        $payload = array_merge([
            'usuario' => $usuario,
            'iat'     => $ahora,
            'exp'     => $expiracion,
            'origen'  => 'API-REST-Laravel',
            'jti'     => bin2hex(random_bytes(16)),
        ], $datosAdicionales);

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadB64 = self::base64UrlEncode($jsonPayload);

        // Firma criptográfica simétrica HMAC-SHA256 usando la clave secreta
        $firma = hash_hmac('sha256', $payloadB64, $clave);

        return $payloadB64 . '.' . $firma;
    }

    /**
     * Decodifica el payload de un token sin validar su firma (para lectura de metadatos).
     */
    public static function decodificarPayload(string $token): ?array
    {
        $partes = explode('.', trim($token));
        if (count($partes) !== 2) {
            return null;
        }

        $json = self::base64UrlDecode($partes[0]);
        $datos = json_decode($json, true);

        return is_array($datos) ? $datos : null;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}

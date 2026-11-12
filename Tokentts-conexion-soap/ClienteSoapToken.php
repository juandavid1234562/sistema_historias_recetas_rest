<?php

namespace TokenttsConexionSoap;

require_once __DIR__ . '/GeneradorTokenSecreto.php';

/**
 * Cliente SOAP para enviar y validar tokens generados desde la aplicación REST.
 *
 * Implementa la comunicación descrita en la guía:
 *  - Envía el token a la operación validarToken() de SOAP para consultar su validez.
 *  - Envía el token en el <soap:Header> (<AuthHeader><token>...</token></AuthHeader>)
 *    para consumir operaciones protegidas del servicio SOAP (como consultarCitas).
 */
class ClienteSoapToken
{
    private string $endpoint;
    private GeneradorTokenSecreto $generador;

    public function __construct(?string $endpoint = null)
    {
        $this->endpoint = $endpoint ?? (getenv('SOAP_CITAS_ENDPOINT_URL') ?: 'http://127.0.0.1/sistema_citas_soap/legacy_soap/soap/server.php');
        $this->generador = new GeneradorTokenSecreto();
    }

    /**
     * Envía un token a la operación 'validarToken' del servicio SOAP
     * para que el servidor SOAP indique si es válido o no mediante la clave secreta.
     *
     * @param string $token
     * @return array [
     *   'valido'     => bool,
     *   'codigo'     => int,
     *   'mensaje'    => string,
     *   'usuario'    => string,
     *   'expiracion' => string,
     *   'raw_xml'    => string
     * ]
     */
    public function validarTokenEnSoap(string $token): array
    {
        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                  xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:urn="ConsultorioSOAP">
   <soapenv:Header/>
   <soapenv:Body>
      <urn:validarToken soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
         <token xsi:type="xsd:string">{$token}</token>
      </urn:validarToken>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        $respuestaHttp = $this->enviarPeticionHttpSoap($xmlPayload, 'ConsultorioSOAP#validarToken');

        if (!$respuestaHttp['exito']) {
            return [
                'valido'     => false,
                'codigo'     => 503,
                'mensaje'    => 'Error de comunicación HTTP con el servidor SOAP: ' . $respuestaHttp['error'],
                'usuario'    => '',
                'expiracion' => '',
                'raw_xml'    => '',
            ];
        }

        return $this->parsearRespuestaValidacionSoap($respuestaHttp['body']);
    }

    /**
     * Consume una operación protegida (consultarCitas) inyectando el token
     * en el Header de seguridad SOAP según la estructura del PDF (Slide 10).
     */
    public function consultarCitasConToken(string $token, int $profesionalId = 0, string $fecha = ''): array
    {
        $tokenXml = htmlspecialchars($token, ENT_XML1);

        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                  xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:urn="ConsultorioSOAP">
   <soapenv:Header>
      <AuthHeader xmlns="urn:UsuariosService">
         <token>{$tokenXml}</token>
      </AuthHeader>
   </soapenv:Header>
   <soapenv:Body>
      <urn:consultarCitas soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
         <profesional_id xsi:type="xsd:int">{$profesionalId}</profesional_id>
         <fecha xsi:type="xsd:string">{$fecha}</fecha>
      </urn:consultarCitas>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        $respuestaHttp = $this->enviarPeticionHttpSoap($xmlPayload, 'ConsultorioSOAP#consultarCitas');

        return $respuestaHttp;
    }

    /**
     * Realiza la llamada HTTP POST al servidor SOAP usando cURL o file_get_contents.
     */
    private function enviarPeticionHttpSoap(string $xml, string $soapAction): array
    {
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $soapAction . '"',
            'Content-Length: ' . strlen($xml),
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($this->endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $error !== '') {
                return ['exito' => false, 'error' => $error, 'status' => $status, 'body' => ''];
            }

            return ['exito' => true, 'status' => $status, 'body' => $body];
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $xml,
                'timeout' => 5,
            ],
        ]);

        $body = @file_get_contents($this->endpoint, false, $context);
        if ($body === false) {
            return ['exito' => false, 'error' => 'No se pudo conectar a ' . $this->endpoint, 'status' => 0, 'body' => ''];
        }

        return ['exito' => true, 'status' => 200, 'body' => $body];
    }

    /**
     * Parsea la respuesta XML devuelta por SOAP para la operación validarToken.
     */
    private function parsearRespuestaValidacionSoap(string $xml): array
    {
        $valido = false;
        $codigo = 0;
        $mensaje = '';
        $usuario = '';
        $expiracion = '';

        if (preg_match('/<valido[^>]*>([^<]+)<\/valido>/i', $xml, $m)) {
            $valido = filter_var(trim($m[1]), FILTER_VALIDATE_BOOLEAN) || trim($m[1]) === '1';
        }
        if (preg_match('/<codigo[^>]*>([^<]+)<\/codigo>/i', $xml, $m)) {
            $codigo = (int) trim($m[1]);
        }
        if (preg_match('/<mensaje[^>]*>([^<]+)<\/mensaje>/i', $xml, $m)) {
            $mensaje = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if (preg_match('/<usuario[^>]*>([^<]+)<\/usuario>/i', $xml, $m)) {
            $usuario = trim($m[1]);
        }
        if (preg_match('/<expiracion[^>]*>([^<]+)<\/expiracion>/i', $xml, $m)) {
            $expiracion = trim($m[1]);
        }

        // Si vino un SOAP Fault
        if (stripos($xml, '<faultstring') !== false) {
            preg_match('/<faultstring[^>]*>([^<]+)<\/faultstring>/i', $xml, $f);
            return [
                'valido'     => false,
                'codigo'     => 500,
                'mensaje'    => 'SOAP Fault: ' . ($f[1] ?? 'Error desconocido del servidor'),
                'usuario'    => '',
                'expiracion' => '',
                'raw_xml'    => $xml,
            ];
        }

        return [
            'valido'     => $valido,
            'codigo'     => $codigo,
            'mensaje'    => $mensaje,
            'usuario'    => $usuario,
            'expiracion' => $expiracion,
            'raw_xml'    => $xml,
        ];
    }
}

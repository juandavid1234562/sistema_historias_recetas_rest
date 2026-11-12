<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TokenttsConexionSoap\ClienteSoapToken;
use TokenttsConexionSoap\ConfiguracionSecreto;
use TokenttsConexionSoap\GeneradorTokenSecreto;

require_once base_path('Tokentts-conexion-soap/ConfiguracionSecreto.php');
require_once base_path('Tokentts-conexion-soap/GeneradorTokenSecreto.php');
require_once base_path('Tokentts-conexion-soap/ClienteSoapToken.php');

/**
 * Controlador API REST para el Punto 6:
 * Conexión, generación y validación de tokens entre REST y SOAP mediante Clave Secreta.
 */
class SoapTokenController extends Controller
{
    private GeneradorTokenSecreto $generador;
    private ClienteSoapToken $clienteSoap;

    public function __construct()
    {
        $this->generador = new GeneradorTokenSecreto();
        $this->clienteSoap = new ClienteSoapToken();
    }

    /**
     * Endpoint 6.1: Genera un token desde la API REST utilizando la clave secreta compartida.
     * POST /api/soap/token/generar
     */
    public function generar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'usuario' => ['nullable', 'string', 'max:100'],
            'minutos' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rol'     => ['nullable', 'string', 'max:50'],
        ]);

        $usuario = $data['usuario'] ?? ($request->user()?->email ?? 'medico_rest@consultorio.com');
        $minutos = (int) ($data['minutos'] ?? ConfiguracionSecreto::MINUTOS_VIGENCIA);
        $adicionales = [];
        if (!empty($data['rol'])) {
            $adicionales['rol'] = $data['rol'];
        }

        $token = $this->generador->generar($usuario, $minutos, $adicionales);
        $payload = GeneradorTokenSecreto::decodificarPayload($token);

        return response()->json([
            'mensaje' => 'Token criptográfico generado exitosamente desde REST usando la clave secreta',
            'token'   => $token,
            'tipo'    => 'HMAC-SHA256 (Clave Secreta Compartida / Stateless)',
            'enfoque' => 'Enfoque 2: Criptografía simétrica entre REST y SOAP',
            'usuario' => $usuario,
            'expira_en_minutos' => $minutos,
            'payload' => $payload,
        ], 200);
    }

    /**
     * Endpoint 6.2: Envía el token a la aplicación SOAP para que indique si es válido o no.
     * POST /api/soap/token/validar
     */
    public function validar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = trim($data['token']);

        // Se envía a la operación 'validarToken' del servicio SOAP
        $resultadoSoap = $this->clienteSoap->validarTokenEnSoap($token);

        // Si el servidor HTTP de Laragon Apache no está levantado en este puerto,
        // ejecutamos la validación mediante el motor de validación SOAP del proyecto legado
        if ($resultadoSoap['codigo'] === 503) {
            $soapValidadorPath = 'C:/laragon/www/sistema_citas_soap/Tokenst-conexion-REST/ValidadorTokenSecreto.php';
            if (file_exists($soapValidadorPath)) {
                require_once $soapValidadorPath;
                $validadorDirecto = new \ValidadorTokenSecreto();
                $resultadoSoap = $validadorDirecto->validar($token);
            }
        }

        $statusCode = ($resultadoSoap['valido'] ?? false) ? 200 : ($resultadoSoap['codigo'] ?? 401);
        if ($statusCode === 0 || $statusCode > 599) {
            $statusCode = 401;
        }

        return response()->json([
            'mensaje'          => 'Resultado de validación devuelto por la aplicación SOAP mediante la clave secreta',
            'token_enviado'    => $token,
            'resultado_soap'   => [
                'valido'     => (bool) ($resultadoSoap['valido'] ?? false),
                'codigo'     => (int) ($resultadoSoap['codigo'] ?? 401),
                'diagnostico'=> $resultadoSoap['mensaje'] ?? 'Sin respuesta',
                'usuario'    => $resultadoSoap['usuario'] ?? null,
                'expiracion' => $resultadoSoap['expiracion'] ?? null,
            ],
        ], $statusCode);
    }

    /**
     * Endpoint 6.3: Consume una operación protegida en SOAP (consultarCitas) enviando el token en el Header.
     * POST /api/soap/citas/consultar
     */
    public function consultarCitasConToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'          => ['nullable', 'string'],
            'profesional_id' => ['nullable', 'integer'],
            'fecha'          => ['nullable', 'date_format:Y-m-d'],
        ]);

        // Si no envía token, se genera uno nuevo con la clave secreta automáticamente
        $token = !empty($data['token'])
            ? trim($data['token'])
            : $this->generador->generar('sistema_rest@consultorio.com', 30);

        $profesionalId = (int) ($data['profesional_id'] ?? 0);
        $fecha = (string) ($data['fecha'] ?? '');

        $respuesta = $this->clienteSoap->consultarCitasConToken($token, $profesionalId, $fecha);

        return response()->json([
            'mensaje'       => 'Petición enviada al servicio SOAP con token en <soap:Header>',
            'token_usado'   => $token,
            'header_formato'=> '<AuthHeader xmlns="urn:UsuariosService"><token>...</token></AuthHeader>',
            'respuesta'     => $respuesta,
        ], 200);
    }
}

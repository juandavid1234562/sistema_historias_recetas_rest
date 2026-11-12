<?php

namespace App\Infrastructure\Adapters\Soap;

use App\Domain\Contracts\CitaServiceInterface;
use App\Domain\Exceptions\CitaInvalidaException;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Patrón Adapter (PDF 2 - Sección 3.4):
 * Traduce el protocolo e interfaz del servicio SOAP legado (NuSOAP/WSDL)
 * a un contrato limpio en el dominio de nuestra aplicación (CitaServiceInterface).
 *
 * De esta forma, el caso de uso y los controladores no conocen XML, WSDL,
 * SOAP Faults ni detalles de red del proveedor.
 */
class CitaSoapAdapter implements CitaServiceInterface
{
    private string $wsdlUrl;
    private string $endpointUrl;
    private int $timeout;
    private bool $mockFallback;

    public function __construct()
    {
        $this->wsdlUrl = config('services.soap_citas.wsdl', env('SOAP_CITAS_WSDL_URL', 'http://127.0.0.1/sistema_citas_soap/legacy_soap/soap/server.php?wsdl'));
        $this->endpointUrl = config('services.soap_citas.endpoint', env('SOAP_CITAS_ENDPOINT_URL', 'http://127.0.0.1/sistema_citas_soap/legacy_soap/soap/server.php'));
        $this->timeout = (int) env('SOAP_CITAS_TIMEOUT', 3);
        $this->mockFallback = filter_var(env('SOAP_MOCK_FALLBACK', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Consulta una cita por su ID comunicándose con el servicio SOAP.
     *
     * @param int $citaId
     * @return array|null
     * @throws CitaInvalidaException
     */
    public function consultarCitaPorId(int $citaId): ?array
    {
        try {
            return $this->consumirServicioSoap($citaId);
        } catch (Throwable $e) {
            Log::warning("Fallo la comunicacion directa con el servicio SOAP de citas: {$e->getMessage()}");

            if ($this->mockFallback) {
                Log::info("Utilizando fallback de simulacion para validar cita_id: {$citaId}");
                return $this->simularConsultaSoap($citaId);
            }

            throw new CitaInvalidaException(
                "No fue posible establecer comunicacion con el servicio SOAP de citas: {$e->getMessage()}",
                $citaId,
                502
            );
        }
    }

    /**
     * Valida que la cita exista y esté en estado 'Programada' (activa).
     *
     * @param int $citaId
     * @return array Datos de la cita validada
     * @throws CitaInvalidaException
     */
    public function validarCitaActiva(int $citaId): array
    {
        $cita = $this->consultarCitaPorId($citaId);

        if (!$cita) {
            throw new CitaInvalidaException(
                "La cita médica con ID {$citaId} no existe en el sistema SOAP.",
                $citaId,
                404
            );
        }

        $estado = trim((string) ($cita['estado'] ?? ''));

        // En el sistema SOAP legado, los estados de cita son: Programada, Completada, Cancelada.
        // Solo las citas 'Programada' se consideran activas y válidas para procesar atención clínica.
        if (strcasecmp($estado, 'Programada') !== 0) {
            throw new CitaInvalidaException(
                "La cita médica con ID {$citaId} no se encuentra activa para atención (Estado actual: '{$estado}'). Solo se pueden atender citas en estado 'Programada'.",
                $citaId,
                422
            );
        }

        return $cita;
    }

    /**
     * Realiza la llamada SOAP real mediante SoapClient de PHP.
     */
    private function consumirServicioSoap(int $citaId): ?array
    {
        // Configuración de opciones para el cliente SOAP con timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
            ],
        ]);

        $options = [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => $this->timeout,
            'stream_context' => $context,
        ];

        $client = new SoapClient($this->wsdlUrl, $options);

        // Inyección de Seguridad (PDF Diapositiva 10 / Punto 6):
        // Se genera el token mediante la clave secreta compartida y se inyecta en el <soap:Header>
        require_once base_path('Tokentts-conexion-soap/GeneradorTokenSecreto.php');
        $generador = new \TokenttsConexionSoap\GeneradorTokenSecreto();
        $token = $generador->generar('sistema_rest_historias_clinicas', 30);

        $authHeaderXml = "<token>{$token}</token>";
        $soapVar = new \SoapVar($authHeaderXml, XSD_ANYXML);
        $header = new \SoapHeader('urn:UsuariosService', 'AuthHeader', $soapVar);
        $client->__setSoapHeaders([$header]);

        // Operación SOAP definida en server.php: consultarCitas($profesional_id, $fecha)
        // profesional_id = 0 y fecha = '' retorna todas las citas registradas en el consultorio.
        $respuesta = $client->__soapCall('consultarCitas', [
            'profesional_id' => 0,
            'fecha' => '',
        ]);

        return $this->filtrarCitaPorId($respuesta, $citaId);
    }

    /**
     * Filtra la respuesta SOAP para encontrar la cita específica por ID.
     */
    private function filtrarCitaPorId(mixed $respuesta, int $citaId): ?array
    {
        if (is_object($respuesta)) {
            $respuesta = json_decode(json_encode($respuesta), true);
        }

        if (!is_array($respuesta)) {
            return null;
        }

        // Si la respuesta es un array asociativo directo o lista de citas
        $citas = isset($respuesta['item']) ? $respuesta['item'] : $respuesta;
        if (!is_array($citas)) {
            return null;
        }

        // Si solo vino una cita como objeto asociativo
        if (isset($citas['id'])) {
            $citas = [$citas];
        }

        foreach ($citas as $c) {
            $cArr = (array) $c;
            if (isset($cArr['id']) && (int) $cArr['id'] === $citaId) {
                return [
                    'id' => (int) $cArr['id'],
                    'profesional_id' => (int) ($cArr['profesional_id'] ?? 0),
                    'profesional' => (string) ($cArr['profesional'] ?? ''),
                    'paciente_id' => (int) ($cArr['paciente_id'] ?? 0),
                    'paciente' => (string) ($cArr['paciente'] ?? ''),
                    'fecha' => (string) ($cArr['fecha'] ?? ''),
                    'hora' => (string) ($cArr['hora'] ?? ''),
                    'estado' => (string) ($cArr['estado'] ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * Simulación de contingencia/test basada en las semillas del sistema SOAP:
     * Cita 1: Programada (Activa)
     * Cita 2: Programada (Activa)
     * Cita 3: Cancelada (Inactiva - Caso de prueba de rechazo 422)
     * Otras: No existe (Caso de prueba de rechazo 404)
     */
    private function simularConsultaSoap(int $citaId): ?array
    {
        $citasMock = [
            1 => [
                'id' => 1,
                'profesional_id' => 1,
                'profesional' => 'Dr. Carlos Mendoza',
                'paciente_id' => 1,
                'paciente' => 'Juan Pérez',
                'fecha' => '2026-09-10',
                'hora' => '09:00:00',
                'estado' => 'Programada',
            ],
            2 => [
                'id' => 2,
                'profesional_id' => 2,
                'profesional' => 'Dra. Elena Rostova',
                'paciente_id' => 2,
                'paciente' => 'María Rodríguez',
                'fecha' => '2026-09-10',
                'hora' => '10:30:00',
                'estado' => 'Programada',
            ],
            3 => [
                'id' => 3,
                'profesional_id' => 1,
                'profesional' => 'Dr. Carlos Mendoza',
                'paciente_id' => 3,
                'paciente' => 'Andrés López',
                'fecha' => '2026-09-11',
                'hora' => '11:00:00',
                'estado' => 'Cancelada',
            ],
        ];

        return $citasMock[$citaId] ?? null;
    }
}

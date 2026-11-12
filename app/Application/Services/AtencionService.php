<?php

namespace App\Application\Services;

use App\Application\Events\AtencionRegistradaEvent;
use App\Domain\Contracts\AtencionRepositoryInterface;
use App\Domain\Contracts\CitaServiceInterface;
use App\Domain\Exceptions\AtencionNoEncontradaException;
use App\Domain\Exceptions\CitaInvalidaException;
use App\Models\AtencionClinica;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio de Aplicación para Atenciones Clínicas (Capa de Aplicación - Arquitectura Limpia).
 *
 * Aplica Inyección de Dependencias por Constructor (PDF 2 - Sección 2.4):
 * Recibe los contratos AtencionRepositoryInterface y CitaServiceInterface desde afuera,
 * desacoplando la lógica de negocio de los detalles técnicos de la BD y de NuSOAP.
 */
class AtencionService
{
    public function __construct(
        private readonly AtencionRepositoryInterface $atencionRepository,
        private readonly CitaServiceInterface $citaService
    ) {}

    /**
     * Registra una nueva atención clínica ejecutando la lógica del puente REST -> SOAP:
     * 1. Valida que la cita_id exista y esté en estado 'Programada' (activa) en el servicio SOAP.
     * 2. Si la cita es inválida o inexistente, se deniega la atención y se lanza CitaInvalidaException.
     * 3. Si la cita es válida, se persiste la atención a través del repositorio.
     * 4. Se emite el evento de dominio (Observer) para auditoría.
     *
     * @param array $datos
     * @return AtencionClinica
     * @throws CitaInvalidaException
     */
    public function registrarAtencion(array $datos): AtencionClinica
    {
        // 1. Lógica del Puente REST -> SOAP (Validación obligatoria)
        $citaValida = $this->citaService->validarCitaActiva((int) $datos['cita_id']);

        // 2. Persistencia desacoplada a través del repositorio (Patrón Repository)
        $atencion = $this->atencionRepository->crear([
            'cita_id' => (int) $datos['cita_id'],
            'diagnostico' => $datos['diagnostico'],
            'observaciones' => $datos['observaciones'] ?? null,
            'fecha_atencion' => $datos['fecha_atencion'],
        ]);

        // 3. Patrón Observer: Disparar evento desacoplado para auditoría o notificaciones
        event(new AtencionRegistradaEvent($atencion, $citaValida));

        return $atencion;
    }

    /**
     * Consulta el detalle de una atención clínica por su ID, incluyendo sus recetas asociadas.
     *
     * @param int $id
     * @return AtencionClinica
     * @throws AtencionNoEncontradaException
     */
    public function consultarDetalle(int $id): AtencionClinica
    {
        $atencion = $this->atencionRepository->obtenerPorIdConRecetas($id);

        if (!$atencion) {
            throw new AtencionNoEncontradaException("No se encontró la atención clínica con ID {$id}.");
        }

        return $atencion;
    }

    /**
     * Lista todas las atenciones clínicas registradas con sus recetas.
     *
     * @return Collection<int, AtencionClinica>
     */
    public function listarAtenciones(): Collection
    {
        return $this->atencionRepository->listarTodas();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\AtencionService;
use App\Domain\Exceptions\AtencionNoEncontradaException;
use App\Domain\Exceptions\CitaInvalidaException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Controlador API REST para Atenciones Clínicas (Capa de Adaptadores / Entrega).
 * Cumple con las especificaciones de PDF 1 (REST / Eloquent) y PDF 2 (DI / Clean Architecture).
 */
class AtencionController extends Controller
{
    /**
     * Inyección de Dependencias por Constructor (PDF 2 - Sección 2.4).
     */
    public function __construct(
        private readonly AtencionService $atencionService
    ) {}

    /**
     * POST /api/atenciones - Registrar una nueva atención clínica.
     * Consume el servicio SOAP para validar cita_id activa antes de persistir.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validación de los datos entrantes (PDF 1 - Sección 3)
        $validatedData = $request->validate([
            'cita_id' => 'required|integer|min:1',
            'diagnostico' => 'required|string|min:3',
            'observaciones' => 'nullable|string',
            'fecha_atencion' => 'required|date',
        ]);

        try {
            // 2. Ejecución de la lógica de negocio a través del servicio
            $atencion = $this->atencionService->registrarAtencion($validatedData);

            // 3. Respuesta JSON estandarizada con código 201 Created (PDF 1 - Sección 3)
            return response()->json([
                'mensaje' => 'Atención clínica registrada exitosamente con validación SOAP aprobada',
                'data' => $atencion,
            ], 201);

        } catch (CitaInvalidaException $e) {
            // Error retornado si la cita no existe o no se encuentra activa en SOAP
            return response()->json([
                'error' => 'Validación SOAP denegada',
                'mensaje' => $e->getMessage(),
                'cita_id' => $e->getCitaId(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422);

        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Error interno al procesar la atención clínica',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/atenciones/{id} - Consultar el detalle de una atención clínica.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $atencion = $this->atencionService->consultarDetalle($id);

            return response()->json([
                'mensaje' => 'Detalle de la atención clínica obtenido exitosamente',
                'data' => $atencion,
            ], 200);

        } catch (AtencionNoEncontradaException $e) {
            return response()->json([
                'error' => 'Recurso no encontrado',
                'mensaje' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * GET /api/atenciones - Listar todas las atenciones registradas.
     */
    public function index(): JsonResponse
    {
        $atenciones = $this->atencionService->listarAtenciones();

        return response()->json([
            'mensaje' => 'Listado de atenciones clínicas obtenido exitosamente',
            'total' => $atenciones->count(),
            'data' => $atenciones,
        ], 200);
    }

    /**
     * GET /api/atenciones/{id}/recetas - Consultar recetas asociadas a una atención clínica específica.
     */
    public function recetas(int $id): JsonResponse
    {
        try {
            $atencion = $this->atencionService->consultarDetalle($id);

            return response()->json([
                'mensaje' => "Recetas asociadas a la atención clínica #{$id}",
                'atencion_id' => $id,
                'total_recetas' => $atencion->recetas->count(),
                'data' => $atencion->recetas,
            ], 200);

        } catch (AtencionNoEncontradaException $e) {
            return response()->json([
                'error' => 'Recurso no encontrado',
                'mensaje' => $e->getMessage(),
            ], 404);
        }
    }
}

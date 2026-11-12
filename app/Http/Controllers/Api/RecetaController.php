<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\RecetaService;
use App\Domain\Exceptions\AtencionNoEncontradaException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Controlador API REST para Recetas Médicas (Capa de Adaptadores / Entrega).
 * Cumple con las directrices de PDF 1 (REST / Eloquent) y PDF 2 (DI / Clean Architecture).
 */
class RecetaController extends Controller
{
    /**
     * Inyección de Dependencias por Constructor (PDF 2 - Sección 2.4).
     */
    public function __construct(
        private readonly RecetaService $recetaService
    ) {}

    /**
     * POST /api/recetas - Crear y asociar una receta médica a una atención existente.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validación de los datos entrantes (PDF 1 - Sección 3)
        $validatedData = $request->validate([
            'atencion_id' => 'required|integer|min:1',
            'medicamento' => 'required|string|max:255',
            'dosis' => 'required|string|max:255',
            'indicaciones' => 'required|string',
        ]);

        try {
            // 2. Creación a través de la capa de aplicación
            $receta = $this->recetaService->crearReceta($validatedData);

            // 3. Respuesta JSON estandarizada con código 201 Created (PDF 1 - Sección 3)
            return response()->json([
                'mensaje' => 'Receta médica creada y asociada exitosamente a la atención clínica',
                'data' => $receta,
            ], 201);

        } catch (AtencionNoEncontradaException $e) {
            return response()->json([
                'error' => 'Atención no encontrada',
                'mensaje' => $e->getMessage(),
            ], 404);

        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Error al crear la receta médica',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
}

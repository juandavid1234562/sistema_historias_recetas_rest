<?php

use App\Http\Controllers\Api\AtencionController;
use App\Http\Controllers\Api\RecetaController;
use App\Http\Controllers\Api\SoapTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran los endpoints REST para el sistema de historias clínicas,
| recetas médicas e integración REST -> SOAP (El prefijo '/api' se añade automáticamente).
|
*/

// ==========================================
// Módulo de Historias Clínicas (Atenciones)
// ==========================================

// 1. POST /api/atenciones - Registrar una nueva atención clínica (con validación previa en SOAP)
Route::post('/atenciones', [AtencionController::class, 'store']);

// 2. GET /api/atenciones/{id} - Consultar el detalle de una atención clínica
Route::get('/atenciones/{id}', [AtencionController::class, 'show'])->whereNumber('id');

// 3. GET /api/atenciones - Listar todas las atenciones registradas
Route::get('/atenciones', [AtencionController::class, 'index']);

// 4. GET /api/atenciones/{id}/recetas - Consultar recetas asociadas a una atención clínica específica
Route::get('/atenciones/{id}/recetas', [AtencionController::class, 'recetas'])->whereNumber('id');

// ==========================================
// Módulo de Recetas Médicas
// ==========================================

// 5. POST /api/recetas - Crear y asociar una receta médica a una atención existente
Route::post('/recetas', [RecetaController::class, 'store']);

// =========================================================================
// Punto 6: Integración REST -> SOAP con Tokens de Clave Secreta Compartida
// =========================================================================

// 6.1 POST /api/soap/token/generar - Crea un token desde REST usando la clave secreta
Route::post('/soap/token/generar', [SoapTokenController::class, 'generar']);

// 6.2 POST /api/soap/token/validar - Envía el token a SOAP y valida si es correcto mediante la clave secreta
Route::post('/soap/token/validar', [SoapTokenController::class, 'validar']);

// 6.3 POST /api/soap/citas/consultar - Consume métodos protegidos de SOAP inyectando el token en el Header
Route::post('/soap/citas/consultar', [SoapTokenController::class, 'consultarCitasConToken']);

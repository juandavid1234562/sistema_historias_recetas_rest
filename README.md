# Sistema de Historias Clínicas y Recetas Médicas (API REST Laravel + SOAP)

Backend desarrollado en **Laravel** bajo **Arquitectura Limpia (Clean Architecture)** y **Patrones de Diseño**, encargado de gestionar historias clínicas (**Atenciones**) y **Recetas Médicas**, integrándose mediante un puente **REST -> SOAP** con el sistema legado de citas médicas en MySQL.

---

## 📋 Tabla de Contenidos
1. [Contexto del Problema y Justificación](#contexto-del-problema-y-justificación)
2. [Entidades Principales y Base de Datos](#entidades-principales-y-base-de-datos)
3. [Arquitectura y Patrones de Diseño (Cumplimiento de PDFs)](#arquitectura-y-patrones-de-diseño)
4. [Endpoints de la API REST](#endpoints-de-la-api-rest)
5. [Lógica de Integración (Puente REST -> SOAP)](#lógica-de-integración-puente-rest---soap)
6. [Instalación y Puesta en Marcha en Laragon](#instalación-y-puesta-en-marcha-en-laragon)
7. [Pruebas Automatizadas (PHPUnit)](#pruebas-automatizadas-phpunit)
8. [Colección de Postman](#colección-de-postman)
9. [Archivos Explicativos de las Guías](#archivos-explicativos-de-las-guías)

---

## 1. Contexto del Problema y Justificación
Actualmente, el consultorio cuenta con la gestión de citas en una arquitectura SOAP/MySQL (`sistema_citas_soap`). Para continuar con la modernización, se desarrolló este backend API REST en Laravel que administra las atenciones clínicas y recetas médicas, consumiendo el servicio SOAP existente para verificar la validez y estado activo de las citas antes de procesar cualquier atención.

---

## 2. Entidades Principales y Base de Datos

### Atencion_Clinica (`atenciones_clinicas`)
- `id` (BIGINT, Clave Primaria)
- `cita_id` (BIGINT, Referencia a la cita validada desde SOAP)
- `diagnostico` (TEXT, Diagnóstico del paciente)
- `observaciones` (TEXT, Notas opcionales de la consulta)
- `fecha_atencion` (DATETIME, Fecha y hora en que se efectuó la atención)
- `created_at` / `updated_at` (Timestamps)

### Receta_Medica (`recetas_medicas`)
- `id` (BIGINT, Clave Primaria)
- `atencion_id` (BIGINT, Clave foránea referenciando `atenciones_clinicas.id` con eliminación en cascada)
- `medicamento` (VARCHAR, Nombre y concentración del fármaco)
- `dosis` (VARCHAR, Dosis prescrita y frecuencia)
- `indicaciones` (TEXT, Indicaciones de administración)
- `created_at` / `updated_at` (Timestamps)

---

## 3. Arquitectura y Patrones de Diseño

El proyecto implementa estrictamente las dos guías académicas:
- **PDF 1 (Desarrollo de API REST con Laravel):** Rutas con prefijo `/api`, migraciones con programación fluida, modelos con `$fillable` (Mass Assignment Protection), validación con `$request->validate()` y respuestas JSON estandarizadas con códigos de estado HTTP (201, 200, 422, 404).
- **PDF 2 (Inyección de Dependencias, Patrones y Arquitectura Limpia):**
  - **Inversión de Dependencias (DI):** Inyección por constructor en todos los controladores y servicios.
  - **Patrón Adapter (`CitaSoapAdapter`):** Encapsula el cliente SOAP legado (`SoapClient`) aislando el protocolo XML/WSDL detrás de `CitaServiceInterface`.
  - **Patrón Repository (`EloquentAtencionRepository`, `EloquentRecetaRepository`):** Aísla la persistencia de la lógica de negocio.
  - **Patrón Observer (`AtencionRegistradaEvent`):** Reacciona desacopladamente tras registrar una atención para auditoría médica.
  - **Arquitectura Limpia en 4 Capas:**
    - `App\Domain`: Contratos (`CitaServiceInterface`, `AtencionRepositoryInterface`, `RecetaRepositoryInterface`) y excepciones de negocio (`CitaInvalidaException`, `AtencionNoEncontradaException`).
    - `App\Application`: Casos de uso (`AtencionService`, `RecetaService`), eventos y oyentes.
    - `App\Infrastructure`: Adaptador SOAP y Repositorios Eloquent.
    - `App\Http\Controllers\Api`: Controladores REST.

---

## 4. Endpoints de la API REST

Todos los endpoints deben recibir el encabezado crítico:
```http
Accept: application/json
Content-Type: application/json
```

| Método | Endpoint | Descripción | Código Éxito |
|---|---|---|---|
| **POST** | `/api/atenciones` | Registra una nueva atención médica (valida previamente en SOAP) | `201 Created` |
| **GET** | `/api/atenciones/{id}` | Consulta el detalle de una atención médica (con sus recetas) | `200 OK` |
| **GET** | `/api/atenciones` | Lista todas las atenciones médicas registradas | `200 OK` |
| **POST** | `/api/recetas` | Crea y asocia una receta médica a una atención existente | `201 Created` |
| **GET** | `/api/atenciones/{id}/recetas` | Consulta las recetas de una atención clínica específica | `200 OK` |

---

## 5. Lógica de Integración (Puente REST -> SOAP)

Antes de registrar una `Atencion_Clinica`:
1. El backend REST invoca el método `validarCitaActiva($citaId)` del `CitaSoapAdapter`.
2. El adaptador consulta el servicio SOAP (`consultarCitas`).
3. **Validación:**
   - Si la cita **no existe** en el sistema SOAP, la API REST deniega la petición retornando **HTTP 404 Not Found**:
     ```json
     {
       "error": "Validación SOAP denegada",
       "mensaje": "La cita médica con ID 9999 no existe en el sistema SOAP.",
       "cita_id": 9999
     }
     ```
   - Si la cita existe pero su estado **no es 'Programada'** (ej. 'Cancelada'), la API REST deniega la petición retornando **HTTP 422 Unprocessable Entity**:
     ```json
     {
       "error": "Validación SOAP denegada",
       "mensaje": "La cita médica con ID 3 no se encuentra activa para atención (Estado actual: 'Cancelada'). Solo se pueden atender citas en estado 'Programada'.",
       "cita_id": 3
     }
     ```
   - Si la cita existe y está activa (`Programada`), se procede con el registro en la base de datos y se responde con **HTTP 201 Created**.

---

## 6. Instalación y Puesta en Marcha en Laragon

### Paso 1: Configurar variables de entorno
El archivo `.env` ya se encuentra configurado. En caso de requerir ajustar la URL del servicio SOAP legado en Apache:
```env
SOAP_CITAS_WSDL_URL="http://127.0.0.1/sistema_citas_soap/legacy_soap/soap/server.php?wsdl"
SOAP_CITAS_ENDPOINT_URL="http://127.0.0.1/sistema_citas_soap/legacy_soap/soap/server.php"
SOAP_MOCK_FALLBACK=true
```
*(Nota: `SOAP_MOCK_FALLBACK=true` permite realizar pruebas inmediatamente sin depender de si Apache está iniciado, simulando con precisión los datos de semilla del servicio SOAP: Cita 1 y 2 activas, Cita 3 cancelada).*

### Paso 2: Ejecutar el servidor de desarrollo
En la terminal dentro de esta carpeta:
```bash
php artisan serve
```
El servidor escuchará en `http://127.0.0.1:8000`.

---

## 7. Pruebas Automatizadas (PHPUnit)
Para verificar la funcionalidad de todos los requerimientos y endpoints:
```bash
php artisan test
```
**Resultado:** 15 pruebas automatizadas pasando con éxito (53 aserciones, 0 fallos).

---

## 8. Colección de Postman
En la raíz del proyecto se encuentra el archivo:
- [`postman_collection.json`](file:///c:/laragon/www/sisttema%20corte%202-3/postman_collection.json)

### Para importarlo en Postman:
1. Abre **Postman**.
2. Haz clic en el botón **Import** (esquina superior izquierda).
3. Arrastra o selecciona el archivo `postman_collection.json`.
4. La colección incluye carpetas para:
   - **1. Atenciones Clínicas**
   - **2. Recetas Médicas**
   - **3. Integración REST-SOAP con Clave Secreta (Punto 6)**

---

## 9. Archivos Explicativos de las Guías
Para una revisión académica exhaustiva, se generaron dos archivos de texto detallados:
- [`GUIA_PDF1_API_REST_LARAVEL.txt`](file:///c:/laragon/www/sisttema%20corte%202-3/GUIA_PDF1_API_REST_LARAVEL.txt): Explicación del cumplimiento de Eloquent, migraciones, `$fillable`, validaciones de request y respuestas estandarizadas.
- [`GUIA_PDF2_PATRONES_ARQUITECTURA_LIMPIA.txt`](file:///c:/laragon/www/sisttema%20corte%202-3/GUIA_PDF2_PATRONES_ARQUITECTURA_LIMPIA.txt): Explicación del cumplimiento de Inyección de Dependencias (DI), Patrón Adapter (SOAP), Patrón Repository, Patrón Observer y las 4 capas de Arquitectura Limpia.

---

## 10. Punto 6: Integración y Validación con Clave Secreta (REST <-> SOAP)

### Concepto de los 2 Enfoques de Tokens
1. **Enfoque 1 (Tokens con Estado / Base de Datos):**
   - En REST: Laravel Sanctum guarda hashes en `personal_access_tokens`.
   - En SOAP: `autenticar` con `wsse:UsernameToken` guarda un token aleatorio hasheado en la tabla `tokens` de MySQL.
2. **Enfoque 2 (Tokens Criptográficos con Clave Secreta Compartida / HMAC-SHA256):**
   - La API REST genera el token firmando el payload con una **clave secreta compartida** (`CLAVE_SECRETA`).
   - El token contiene la identidad (`usuario`), emisión (`iat`), expiración (`exp`) y origen.
   - El token se envía a la aplicación SOAP.
   - El servidor SOAP ejecuta la validación recalculando la firma criptográfica con su propia copia de la clave secreta (`hash_hmac('sha256')`).
   - Si la firma coincide y no ha expirado, SOAP certifica que el token es **VÁLIDO** y autoriza el consumo de métodos protegidos. Si la firma difiere o fue alterada, SOAP indica que es **INVÁLIDO**.

### Carpetas de Conexión Implementadas:
- En REST: [`Tokentts-conexion-soap/`](file:///c:/laragon/www/sisttema%20corte%202-3/Tokentts-conexion-soap)
  - `ConfiguracionSecreto.php`
  - `GeneradorTokenSecreto.php`
  - `ClienteSoapToken.php`
  - `probar_conexion_soap.php` (demostración en consola)
  - `README.md`
- En SOAP: `C:\laragon\www\sistema_citas_soap\Tokenst-conexion-REST\`
  - `ConfiguracionSecreto.php`
  - `ValidadorTokenSecreto.php`
  - `probar_validacion_rest.php` (demostración en consola)
  - `README.md`

### Endpoints REST para el Punto 6:
- `POST /api/soap/token/generar`: Genera el token con la clave secreta.
- `POST /api/soap/token/validar`: Envía el token a SOAP y devuelve el dictamen de validación.
- `POST /api/soap/citas/consultar`: Consume `consultarCitas` inyectando el token en el `<soap:Header>`.

### Cómo probar la integración desde terminal:
```bash
# Probar desde el proyecto REST:
php Tokentts-conexion-soap/probar_conexion_soap.php

# Probar desde el proyecto SOAP:
php C:\laragon\www\sistema_citas_soap\Tokenst-conexion-REST\probar_validacion_rest.php
```


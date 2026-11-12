# Conexión REST -> SOAP: Generación y Validación de Tokens con Clave Secreta

## Contexto y Cumplimiento del Requerimiento (Punto 6)
Este módulo aborda los **2 enfoques** vistos en clase sobre el manejo y validación de tokens entre arquitecturas REST y SOAP:
1. **Enfoque 1 (Tokens con Estado / Base de Datos):**
   - En Laravel REST: Tokens personales gestionados con Sanctum persistidos en `personal_access_tokens`.
   - En SOAP NuSOAP: Tokens emitidos tras autenticación con `wsse:UsernameToken` y guardados en la tabla MySQL `tokens`.
2. **Enfoque 2 (Tokens Criptográficos con Clave Secreta Compartida - HMAC-SHA256):**
   - La API REST genera un token firmado simétricamente con una **Clave Secreta** (`CLAVE_SECRETA`).
   - El token contiene la identidad (`usuario`), fecha de emisión (`iat`), expiración (`exp`) y origen (`API-REST-Laravel`).
   - El token se transmite a la aplicación SOAP.
   - El servidor SOAP ejecuta la validación recalculando la firma HMAC con la misma clave secreta, certificando si es auténtico, si ha expirado o si fue manipulado.

---

## Archivos del Módulo en REST (`Tokentts-conexion-soap/`)
1. **`ConfiguracionSecreto.php`**: Almacena la clave secreta compartida (`Consultorio_Secret_Key_Corte2_3_2026!#`) y parámetros de duración.
2. **`GeneradorTokenSecreto.php`**: Clase PHP encargada de ensamblar el payload y generar la firma HMAC-SHA256:
   ```php
   $token = $payloadBase64Url . '.' . hash_hmac('sha256', $payloadBase64Url, $claveSecreta);
   ```
3. **`ClienteSoapToken.php`**: Conector que implementa las llamadas al Web Service SOAP:
   - `validarTokenEnSoap($token)`: Invoca la operación SOAP `validarToken` y procesa la respuesta XML.
   - `consultarCitasConToken($token, ...)`: Envía el token dentro del `<soap:Header>` mediante la estructura `<AuthHeader><token>...</token></AuthHeader>` (según la diapositiva 10 de la guía de seguridad).
4. **`probar_conexion_soap.php`**: Script de consola que ejecuta la demostración completa:
   - Generación del token en REST con la clave secreta.
   - Envío y validación en SOAP (devuelve si es válido o no).
   - Prueba con token alterado (SOAP lo rechaza por firma inválida).
   - Prueba con clave incorrecta (SOAP lo rechaza).

---

## Ejecución de Pruebas
Para ejecutar la prueba de extremo a extremo desde terminal:
```bash
php Tokentts-conexion-soap/probar_conexion_soap.php
```

---

## Integración en la API REST (Endpoints Laravel)
Se incorporaron los siguientes endpoints en `routes/api.php` para pruebas mediante Postman:
- **`POST /api/soap/token/generar`**: Emite un nuevo token con la clave secreta.
- **`POST /api/soap/token/validar`**: Envía un token a SOAP para certificar su validez.
- **`POST /api/soap/citas/consultar`**: Consume el servicio SOAP de citas autenticándose con el token en el Header SOAP.

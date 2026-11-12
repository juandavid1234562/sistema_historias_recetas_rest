<?php

namespace Tests\Feature;

use Tests\TestCase;
use TokenttsConexionSoap\ConfiguracionSecreto;
use TokenttsConexionSoap\GeneradorTokenSecreto;

class SoapTokenTest extends TestCase
{
    /**
     * Test 6.1: Generar token desde REST usando la clave secreta.
     */
    public function test_generar_token_con_clave_secreta_retorna_200(): void
    {
        $response = $this->postJson('/api/soap/token/generar', [
            'usuario' => 'medico_prueba@consultorio.com',
            'minutos' => 45,
            'rol'     => 'Especialista',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mensaje',
                'token',
                'tipo',
                'usuario',
                'expira_en_minutos',
                'payload',
            ]);

        $this->assertEquals('medico_prueba@consultorio.com', $response->json('usuario'));
        $this->assertEquals(45, $response->json('expira_en_minutos'));
        $this->assertStringContainsString('.', $response->json('token'));
    }

    /**
     * Test 6.2: Validar un token legítimo generado con la clave secreta compartida.
     */
    public function test_validar_token_valido_retorna_aprobado_por_soap(): void
    {
        // 1. Generar token legítimo
        $generador = new GeneradorTokenSecreto();
        $token = $generador->generar('doctor_pediatra@consultorio.com', 30);

        // 2. Enviar a validación
        $response = $this->postJson('/api/soap/token/validar', [
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'resultado_soap' => [
                    'valido' => true,
                    'codigo' => 200,
                    'usuario' => 'doctor_pediatra@consultorio.com',
                ],
            ]);
    }

    /**
     * Test 6.3: Validar un token alterado / manipulado (la firma no coincide).
     */
    public function test_validar_token_alterado_retorna_401_rechazado(): void
    {
        $generador = new GeneradorTokenSecreto();
        $tokenValido = $generador->generar('usuario_real@consultorio.com', 30);

        $partes = explode('.', $tokenValido);
        $payloadFalso = GeneradorTokenSecreto::base64UrlEncode(json_encode([
            'usuario' => 'suplantador_infiltrado',
            'exp'     => time() + 3600,
        ]));
        $tokenManipulado = $payloadFalso . '.' . $partes[1];

        $response = $this->postJson('/api/soap/token/validar', [
            'token' => $tokenManipulado,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'resultado_soap' => [
                    'valido' => false,
                    'codigo' => 401,
                ],
            ]);
    }

    /**
     * Test 6.4: Validar un token firmado con una clave secreta equivocada.
     */
    public function test_validar_token_con_clave_incorrecta_retorna_401(): void
    {
        $tokenFalso = GeneradorTokenSecreto::generarConClave(
            'medico_externo@consultorio.com',
            30,
            'clave_secreta_totalmente_equivocada_12345'
        );

        $response = $this->postJson('/api/soap/token/validar', [
            'token' => $tokenFalso,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'resultado_soap' => [
                    'valido' => false,
                    'codigo' => 401,
                ],
            ]);
    }
}

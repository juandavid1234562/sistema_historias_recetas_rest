<?php

namespace Tests\Feature;

use App\Domain\Contracts\CitaServiceInterface;
use App\Models\AtencionClinica;
use App\Models\RecetaMedica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicaApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Registrar atención exitosamente cuando la cita SOAP está activa ('Programada').
     */
    public function test_registrar_atencion_con_cita_soap_activa_retorna_201(): void
    {
        $payload = [
            'cita_id' => 1, // En el simulador/SOAP Cita 1 está en estado 'Programada'
            'diagnostico' => 'Faringitis aguda con dolor e inflamación',
            'observaciones' => 'Reposo de 48 horas y abundantes líquidos',
            'fecha_atencion' => '2026-10-15 08:30:00',
        ];

        $response = $this->postJson('/api/atenciones', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'mensaje',
                'data' => [
                    'id',
                    'cita_id',
                    'diagnostico',
                    'observaciones',
                    'fecha_atencion',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'mensaje' => 'Atención clínica registrada exitosamente con validación SOAP aprobada',
                'data' => [
                    'cita_id' => 1,
                    'diagnostico' => 'Faringitis aguda con dolor e inflamación',
                ],
            ]);

        $this->assertDatabaseHas('atenciones_clinicas', [
            'cita_id' => 1,
            'diagnostico' => 'Faringitis aguda con dolor e inflamación',
        ]);
    }

    /**
     * Test 2: Denegar registro si la cita no existe en el sistema SOAP (retorna 404).
     */
    public function test_denegar_registro_si_cita_no_existe_en_soap_retorna_404(): void
    {
        $payload = [
            'cita_id' => 9999, // Cita inexistente
            'diagnostico' => 'Consulta de control general',
            'fecha_atencion' => '2026-10-15 09:00:00',
        ];

        $response = $this->postJson('/api/atenciones', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Validación SOAP denegada',
                'cita_id' => 9999,
            ]);
    }

    /**
     * Test 3: Denegar registro si la cita en SOAP está Cancelada / Inactiva (retorna 422).
     */
    public function test_denegar_registro_si_cita_en_soap_esta_inactiva_retorna_422(): void
    {
        $payload = [
            'cita_id' => 3, // En el mock/SOAP la cita 3 está 'Cancelada'
            'diagnostico' => 'Paciente presenta cefalea tensional',
            'fecha_atencion' => '2026-10-15 10:00:00',
        ];

        $response = $this->postJson('/api/atenciones', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validación SOAP denegada',
                'cita_id' => 3,
            ]);
    }

    /**
     * Test 4: Validación de campos requeridos (PDF 1 - Header Accept: application/json).
     */
    public function test_validacion_campos_requeridos_en_atencion(): void
    {
        $response = $this->postJson('/api/atenciones', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cita_id', 'diagnostico', 'fecha_atencion']);
    }

    /**
     * Test 5: Consultar detalle de una atención clínica con sus recetas.
     */
    public function test_consultar_detalle_de_atencion_retorna_200(): void
    {
        $atencion = AtencionClinica::create([
            'cita_id' => 1,
            'diagnostico' => 'Gastroenteritis viral aguda',
            'observaciones' => 'Dieta blanda por 3 días',
            'fecha_atencion' => now(),
        ]);

        $response = $this->getJson("/api/atenciones/{$atencion->id}");

        $response->assertStatus(200)
            ->assertJson([
                'mensaje' => 'Detalle de la atención clínica obtenido exitosamente',
                'data' => [
                    'id' => $atencion->id,
                    'cita_id' => 1,
                    'diagnostico' => 'Gastroenteritis viral aguda',
                ],
            ]);
    }

    /**
     * Test 6: Listar todas las atenciones registradas.
     */
    public function test_listar_todas_las_atenciones_retorna_200(): void
    {
        AtencionClinica::create([
            'cita_id' => 1,
            'diagnostico' => 'Consulta 1',
            'fecha_atencion' => now(),
        ]);

        AtencionClinica::create([
            'cita_id' => 2,
            'diagnostico' => 'Consulta 2',
            'fecha_atencion' => now(),
        ]);

        $response = $this->getJson('/api/atenciones');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mensaje',
                'total',
                'data',
            ])
            ->assertJson([
                'total' => 2,
            ]);
    }

    /**
     * Test 7: Crear y asociar una receta médica a una atención clínica existente.
     */
    public function test_crear_receta_medica_asociada_retorna_201(): void
    {
        $atencion = AtencionClinica::create([
            'cita_id' => 1,
            'diagnostico' => 'Infección respiratoria superior',
            'fecha_atencion' => now(),
        ]);

        $payload = [
            'atencion_id' => $atencion->id,
            'medicamento' => 'Amoxicilina 500mg',
            'dosis' => '1 cápsula cada 8 horas',
            'indicaciones' => 'Tomar por 7 días después de las comidas',
        ];

        $response = $this->postJson('/api/recetas', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'mensaje' => 'Receta médica creada y asociada exitosamente a la atención clínica',
                'data' => [
                    'atencion_id' => $atencion->id,
                    'medicamento' => 'Amoxicilina 500mg',
                    'dosis' => '1 cápsula cada 8 horas',
                ],
            ]);

        $this->assertDatabaseHas('recetas_medicas', [
            'atencion_id' => $atencion->id,
            'medicamento' => 'Amoxicilina 500mg',
        ]);
    }

    /**
     * Test 8: Denegar creación de receta si la atención clínica no existe (retorna 404).
     */
    public function test_denegar_receta_si_atencion_no_existe_retorna_404(): void
    {
        $payload = [
            'atencion_id' => 9999,
            'medicamento' => 'Paracetamol 500mg',
            'dosis' => '1 tableta cada 8 horas',
            'indicaciones' => 'Si persiste el dolor o fiebre',
        ];

        $response = $this->postJson('/api/recetas', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Atención no encontrada',
            ]);
    }

    /**
     * Test 9: Consultar recetas asociadas a una atención clínica específica.
     */
    public function test_consultar_recetas_de_una_atencion_retorna_200(): void
    {
        $atencion = AtencionClinica::create([
            'cita_id' => 2,
            'diagnostico' => 'Hipertensión arterial estadio 1',
            'fecha_atencion' => now(),
        ]);

        RecetaMedica::create([
            'atencion_id' => $atencion->id,
            'medicamento' => 'Losartán 50mg',
            'dosis' => '1 tableta cada mañana',
            'indicaciones' => 'Tomar en ayunas',
        ]);

        RecetaMedica::create([
            'atencion_id' => $atencion->id,
            'medicamento' => 'Hidroclorotiazida 12.5mg',
            'dosis' => '1 tableta cada mañana',
            'indicaciones' => 'Junto con el Losartán',
        ]);

        $response = $this->getJson("/api/atenciones/{$atencion->id}/recetas");

        $response->assertStatus(200)
            ->assertJson([
                'mensaje' => "Recetas asociadas a la atención clínica #{$atencion->id}",
                'atencion_id' => $atencion->id,
                'total_recetas' => 2,
            ]);
    }
}

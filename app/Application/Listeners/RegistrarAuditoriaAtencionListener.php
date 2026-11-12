<?php

namespace App\Application\Listeners;

use App\Application\Events\AtencionRegistradaEvent;
use Illuminate\Support\Facades\Log;

/**
 * Observador desacoplado (PDF 2 - Sección 3.5):
 * Este observador solo se encarga de auditar y registrar la atención realizada
 * a partir de la cita validada por SOAP.
 */
class RegistrarAuditoriaAtencionListener
{
    public function handle(AtencionRegistradaEvent $event): void
    {
        Log::info('AUDITORIA MEDICA: Atencion clinica registrada exitosamente.', [
            'atencion_id' => $event->atencion->id,
            'cita_id' => $event->atencion->cita_id,
            'fecha_atencion' => $event->atencion->fecha_atencion,
            'diagnostico' => $event->atencion->diagnostico,
            'cita_info' => $event->datosCita,
        ]);
    }
}

<?php

namespace App\Application\Events;

use App\Models\AtencionClinica;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Patrón Observer (PDF 2 - Sección 3.5):
 * Evento emitido cuando se registra exitosamente una atención clínica.
 * Permite que componentes desacoplados (auditoría, notificaciones) reaccionen
 * sin que el servicio de atención conozca los detalles de esas acciones.
 */
class AtencionRegistradaEvent
{
    use Dispatchable, SerializesModels;

    public AtencionClinica $atencion;
    public array $datosCita;

    public function __construct(AtencionClinica $atencion, array $datosCita = [])
    {
        $this->atencion = $atencion;
        $this->datosCita = $datosCita;
    }
}

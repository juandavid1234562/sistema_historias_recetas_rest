<?php

namespace App\Providers;

use App\Application\Events\AtencionRegistradaEvent;
use App\Application\Listeners\RegistrarAuditoriaAtencionListener;
use App\Domain\Contracts\AtencionRepositoryInterface;
use App\Domain\Contracts\CitaServiceInterface;
use App\Domain\Contracts\RecetaRepositoryInterface;
use App\Infrastructure\Adapters\Soap\CitaSoapAdapter;
use App\Infrastructure\Repositories\EloquentAtencionRepository;
use App\Infrastructure\Repositories\EloquentRecetaRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Punto de composición para la Inyección de Dependencias (PDF 2 - Sección 2.2 y 4.6):
     * Vinculación de contratos (interfaces) a sus implementaciones concretas.
     */
    public function register(): void
    {
        // Enlace de repositorios (Patrón Repository)
        $this->app->bind(AtencionRepositoryInterface::class, EloquentAtencionRepository::class);
        $this->app->bind(RecetaRepositoryInterface::class, EloquentRecetaRepository::class);

        // Enlace del adaptador SOAP para el puente REST -> SOAP (Patrón Adapter)
        $this->app->bind(CitaServiceInterface::class, CitaSoapAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registro del observador desacoplado (Patrón Observer - PDF 2 Sección 3.5)
        Event::listen(
            AtencionRegistradaEvent::class,
            RegistrarAuditoriaAtencionListener::class
        );
    }
}

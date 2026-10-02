<?php

namespace App\Providers;

use App\Events\ContratoCreado;
use App\Events\PagoRegistrado;
use App\Events\ReservaCreada;
use App\Events\ActualizacionDatos;
use App\Listeners\SendCentralizedMailNotification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app['events']->listen(ReservaCreada::class, SendCentralizedMailNotification::class);
        $this->app['events']->listen(ContratoCreado::class, SendCentralizedMailNotification::class);
        $this->app['events']->listen(PagoRegistrado::class, SendCentralizedMailNotification::class);
        $this->app['events']->listen(ActualizacionDatos::class, SendCentralizedMailNotification::class);
    }
}

<?php

namespace App\Providers;

use App\Models\ListaEscolarDetalle;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Observers\ListaEscolarDetalleObserver;
use App\Observers\ProductoObserver;
use App\Observers\SucursalObserver;
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
        Sucursal::observe(SucursalObserver::class);
        Producto::observe(ProductoObserver::class);
        ListaEscolarDetalle::observe(ListaEscolarDetalleObserver::class);
    }
}

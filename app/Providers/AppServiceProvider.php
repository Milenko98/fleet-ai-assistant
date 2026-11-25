<?php

namespace App\Providers;

use App\Ai\FleetAgent;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Agent se uvijek dohvaća iz containera, pa ga testovi mogu zamijeniti verzijom s lažnim API-jem
        $this->app->bind(FleetAgent::class, fn () => new FleetAgent);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

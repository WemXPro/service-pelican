<?php

namespace App\Services\Pelican\Providers;

use Illuminate\Support\ServiceProvider;

class PelicanServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Pelican';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'pelican';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
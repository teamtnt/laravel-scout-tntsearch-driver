<?php

namespace TeamTNT\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchScoutServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app[EngineManager::class]->extend('tntsearch', function () {
            $driver = config('database.default');
            $tnt = new TNTSearch();
            $config = config('scout.tntsearch') + config("database.connections.$driver");
            $tnt->loadConfig($config);

            return new Engines\TNTSearchEngine($tnt);
        });
    }
}

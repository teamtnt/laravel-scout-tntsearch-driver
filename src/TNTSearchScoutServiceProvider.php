<?php

namespace TeamTNT\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchScoutServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app[EngineManager::class]->extend('tntsearch', function () {
            $tnt = new TNTSearch();
            $driver = config('database.default');
            $config = config('scout.tntsearch') + config("database.connections.$driver");

            $tnt->loadConfig($config);
            $tnt->setDatabaseHandle(app('db')->connection()->getPdo());

            return new Engines\TNTSearchEngine($tnt);
        });
    }
}

<?php namespace TeamTNT\Scout;

use TeamTNT\TNTSearch\TNTGeoSearch;
use TeamTNT\TNTSearch\TNTSearch;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Builder;
use Illuminate\Support\ServiceProvider;
use TeamTNT\Scout\Console\ImportCommand;
use TeamTNT\Scout\Engines\TNTSearchEngine;

class TNTSearchScoutServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app[EngineManager::class]->extend('tntsearch', function ($app) {
            $tnt = new TNTSearch();
            
            $driver = config('database.default');
            $config = config('scout.tntsearch') + config("database.connections.{$driver}");

            $tnt->loadConfig($config);
            $tnt->setDatabaseHandle(app('db')->connection()->getPdo());
            
            $this->setFuzziness($tnt);
            $this->setAsYouType($tnt);

            $geotnt = null;
            if (!empty($config['geoIndex'])) {
                $geotnt = new TNTGeoSearch();

                $geotnt->loadConfig($config);
                $geotnt->setDatabaseHandle(app('db')->connection()->getPdo());
            }

            return new TNTSearchEngine($tnt, $geotnt);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);
        }

        Builder::macro('constrain', function($constraints) {
            $this->constraints = $constraints;
            return $this;
        });
    }

    protected function setFuzziness($tnt)
    {
        $tnt->fuzziness            = config('scout.tntsearch.fuzziness', $tnt->fuzziness);
        $tnt->fuzzy_distance       = config('scout.tntsearch.fuzzy.distance', $tnt->fuzzy_distance);
        $tnt->fuzzy_prefix_length  = config('scout.tntsearch.fuzzy.prefix_length', $tnt->fuzzy_prefix_length);
        $tnt->fuzzy_max_expansions = config('scout.tntsearch.fuzzy.max_expansions', $tnt->fuzzy_max_expansions);
    }

    protected function setAsYouType($tnt)
    {
        $tnt->asYouType = config('scout.tntsearch.asYouType', $tnt->asYouType);
    }
}

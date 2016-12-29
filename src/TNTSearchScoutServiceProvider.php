<?php

namespace TeamTNT\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use TeamTNT\Scout\Console\ImportCommand;
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
            $this->setFuzziness($tnt);
            $this->setAsYouType($tnt);

            return new Engines\TNTSearchEngine($tnt);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);
        }
    }

    private function setFuzziness($tnt)
    {
        $fuzziness = config('scout.tntsearch.fuzziness');
        $prefix_length = config('scout.tntsearch.fuzzy.prefix_length');
        $max_expansions = config('scout.tntsearch.fuzzy.max_expansions');
        $distance = config('scout.tntsearch.fuzzy.distance');


        $tnt->fuzziness = isset($fuzziness) ? $fuzziness : $tnt->fuzziness;
        $tnt->fuzzy_prefix_length = isset($prefix_length) ? $prefix_length : $tnt->fuzzy_prefix_length;
        $tnt->fuzzy_max_expansions = isset($max_expansions) ? $max_expansions : $tnt->fuzzy_max_expansions;
        $tnt->fuzzy_distance = isset($distance) ? $distance : $tnt->fuzzy_distance;
    }

    private function setAsYouType($tnt)
    {
        $asYouType = config('scout.tntsearch.asYouType');

        $tnt->asYouType = isset($asYouType) ? $asYouType : $tnt->asYouType;
    }
}

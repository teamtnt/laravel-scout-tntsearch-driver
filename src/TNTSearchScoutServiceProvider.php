<?php namespace TeamTNT\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use TeamTNT\Scout\Console\ImportCommand;
use TeamTNT\Scout\Console\StatusCommand;
use TeamTNT\Scout\Engines\TNTSearchEngine;
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
        $this->app[EngineManager::class]->extend('tntsearch', function ($app) {
            $tnt = new TNTSearch();

            $driver = config('database.default');
            $config = config('scout.tntsearch') + config("database.connections.{$driver}");

            $tnt->loadConfig($config);
            $tnt->setDatabaseHandle(app('db')->connection()->getPdo());
            $tnt->maxDocs = config('scout.tntsearch.maxDocs', 500);

            $this->setFuzziness($tnt);
            $this->setAsYouType($tnt);

            return new TNTSearchEngine($tnt);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
                StatusCommand::class
            ]);
        }

        Builder::macro('constrain', function ($constraints) {
            $this->constraints = $constraints;
            return $this;
        });
    }

    protected function setFuzziness($tnt)
    {
        $tnt->setFuzziness(config('scout.tntsearch.fuzziness', $tnt->getFuzziness()));
        $tnt->setFuzzyDistance(config('scout.tntsearch.fuzzy.distance', $tnt->getFuzzyDistance()));
        $tnt->setFuzzyPrefixLength(config('scout.tntsearch.fuzzy.prefix_length', $tnt->getFuzzyPrefixLength()));
        $tnt->setFuzzyMaxExpansions(config('scout.tntsearch.fuzzy.max_expansions', $tnt->getFuzzyMaxExpansions()));
        $tnt->setFuzzyNoLimit(config('scout.tntsearch.fuzzy.no_limit', $tnt->getFuzzyNoLimit()));
    }

    protected function setAsYouType($tnt)
    {
        $tnt->setAsYouType(config('scout.tntsearch.asYouType', $tnt->getAsYouType()));
    }
}

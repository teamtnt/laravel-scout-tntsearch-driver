<?php
namespace TeamTNT\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use TeamTNT\Scout\Console\ImportCommand;
use TeamTNT\Scout\Console\StatusCommand;
use TeamTNT\Scout\Engines\TNTSearchEngine;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchScoutServiceProvider extends ServiceProvider
{
    public $constraints;
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
            $tnt->engine->maxDocs = config('scout.tntsearch.maxDocs', 500);

            // Call via the fully-qualified class. Laravel 13's Manager::extend rebinds
            // the closure's $this AND its scope to the Manager, so $this->setFuzziness()
            // would proxy through Manager::__call -> $this->driver()->setFuzziness(...),
            // which recursively invokes this same closure and exhausts memory.
            // self::setFuzziness() falls into the same trap because PHP resolves it via
            // the closure's rebound scope.
            TNTSearchScoutServiceProvider::setFuzziness($tnt);
            TNTSearchScoutServiceProvider::setAsYouType($tnt);

            return new TNTSearchEngine($tnt);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
                StatusCommand::class,
            ]);
        }

        Builder::macro('constrain', function ($constraints) {
            $this->constraints = $constraints;
            return $this;
        });
    }

    public static function setFuzziness($tnt)
    {
        $tnt->setFuzziness(config('scout.tntsearch.fuzziness', $tnt->getFuzziness()));
        $tnt->setFuzzyDistance(config('scout.tntsearch.fuzzy.distance', $tnt->getFuzzyDistance()));
        $tnt->setFuzzyPrefixLength(config('scout.tntsearch.fuzzy.prefix_length', $tnt->getFuzzyPrefixLength()));
        $tnt->setFuzzyMaxExpansions(config('scout.tntsearch.fuzzy.max_expansions', $tnt->getFuzzyMaxExpansions()));
        $tnt->setFuzzyNoLimit(config('scout.tntsearch.fuzzy.no_limit', $tnt->getFuzzyNoLimit()));
    }

    public static function setAsYouType($tnt)
    {
        $tnt->setAsYouType(config('scout.tntsearch.asYouType', $tnt->getAsYouType()));
    }
}

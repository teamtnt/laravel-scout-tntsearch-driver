<?php

namespace TeamTNT\Scout\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Finder\Finder;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTSearch;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:status {model? : The name of the model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Status of the search index by TNTSeach';

    /**
     * @var array
     */
    private static $declaredClasses;


    /**
     * Execute the console command.
     *
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     *
     * @return void
     */
    public function handle(Dispatcher $events)
    {

        $searchableModels = $this->getSearchableModels();

        $this->output->text('🔎 Analysing information from: <info>['.implode(',', $searchableModels).']</info>');
        $this->output->newLine();
        $this->output->progressStart(count($searchableModels));

        $headers = ['Searchable', 'Index', 'Indexed Columns', 'Index Records', 'DB Records', 'Records difference'];
        $rows = [];
        foreach ($searchableModels as $class) {
            $model = new $class();

            $tnt = $this->loadTNTEngine($model);
            $indexName = $model->searchableAs().'.index';

            try {
                $tnt->selectIndex($indexName);
                $rowsIndexed = $tnt->totalDocumentsInCollection();
            } catch (IndexNotFoundException $e) {
                $rowsIndexed = 0;
            }

            $rowsTotal = $model->count();
            $recordsDifference = $rowsTotal - $rowsIndexed;

            $indexedColumns = $rowsTotal ? implode(",", array_keys($model->first()->toSearchableArray())) : '';

            if($recordsDifference == 0) {
                $recordsDifference = '<fg=green>Synchronized</>';
            } else {
                $recordsDifference = "<fg=red>$recordsDifference</>";
            }

            array_push($rows, [$class, $indexName, $indexedColumns, $rowsIndexed, $rowsTotal, $recordsDifference]);

        }

        $this->output->progressFinish();
        $this->output->table($headers, $rows);
    }

    /**
     * @return array
     */
    private function getProjectClasses(): array
    {

        if (self::$declaredClasses === null) {
            $configFiles = Finder::create()->files()
                ->name('*.php')->notName('*.blade.php')
                ->in(config('scout.tntsearch.modelPath', app()->path()));

            foreach ($configFiles->files() as $file) {
                try {
                    require_once $file;
                } catch (\Exception $e) {
                    //skiping if the file cannot be loaded
                } catch (\Throwable $e) {
                    //skiping if the file cannot be loaded
                }
            }

            self::$declaredClasses = get_declared_classes();
        }

        return self::$declaredClasses;
    }

    /**
     * @return array|void
     */
    private function getSearchableModelsFromClasses($trait = 'Laravel\Scout\Searchable')
    {
        $projectClasses = $this->getProjectClasses();
        $classes = array_filter(
            $projectClasses,
            $this->isSearchableModel($trait)
        );

        return $classes;
    }

    /**
     * @return array
     */
    private function getSearchableModels()
    {
        $searchableModels = (array)$this->argument('model');
        if (empty($searchableModels)) {
            $searchableModels = $this->getSearchableModelsFromClasses();
        }

        return $searchableModels;
    }

    /**
     * @param $trait
     * @return \Closure
     */
    private function isSearchableModel($trait)
    {
        return function ($className) use ($trait) {
            $traits = class_uses_recursive($className);

            return isset($traits[$trait]);
        };
    }

    /**
     * @param $model
     * @return TNTSearch
     */
    private function loadTNTEngine($model)
    {
        $tnt = new TNTSearch();

        $driver = $model->getConnectionName() ?: config('database.default');
        $config = config('scout.tntsearch') + config("database.connections.$driver");
        $tnt->loadConfig($config);

        return $tnt;
    }
}

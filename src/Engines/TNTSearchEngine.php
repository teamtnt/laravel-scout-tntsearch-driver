<?php

namespace TeamTNT\Scout\Engines;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchEngine extends Engine
{
    /**
     * @var TNTSearch
     */
    protected $tnt;

    /**
     * Create a new engine instance.
     *
     * @param TNTSearch $tnt
     */
    public function __construct(TNTSearch $tnt)
    {
        $this->tnt = $tnt;
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
     *
     * @return void
     */
    public function update($models)
    {
        $this->initIndex($models->first());
        $models->each(function ($model) {
            $searchableFields = $model->toSearchableArray();

            $this->tnt->selectIndex("{$model->searchableAs()}.index");
            $index = $this->tnt->getIndex();
            $index->setPrimaryKey($model->getKeyName());

            if ($model->getKey()) {
                $index->update($model->getKey(), $searchableFields);
            } else {
                $index->insert($searchableFields);
            }
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param Collection $models
     *
     * @return void
     */
    public function delete($models)
    {
        $this->initIndex($models->first());
        $models->each(function ($model) {
            $this->tnt->selectIndex("{$model->searchableAs()}.index");
            $index = $this->tnt->getIndex();
            $index->setPrimaryKey($model->getKeyName());
            $index->delete($model->id);
        });
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     *
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int     $perPage
     * @param int     $page
     *
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $builder->limit = 1000;
        $results = $this->performSearch($builder);
        $chunks = array_chunk($results['ids'], $perPage);

        if (!empty($chunks)) {
            if (array_key_exists($page - 1, $chunks)) {
                $results['ids'] = $chunks[$page - 1];
            } else {
                $results['ids'] = end($chunks);
            }
        }

        return $results;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder)
    {
        $index = $builder->index ?: $builder->model->searchableAs();
        $limit = $builder->limit ?: 15;
        $this->tnt->selectIndex("{$index}.index");

        return $this->tnt->search($builder->query, $limit);
    }

    /**
     * Get the filter array for the query.
     *
     * @param Builder $builder
     *
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            return $key.'='.$value;
        })->values()->all();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param mixed                               $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return Collection
     */
    public function map($results, $model)
    {
        if (count($results['ids']) === 0) {
            return Collection::make();
        }

        $keys = collect($results['ids'])->values()->all();
        $models = $model->whereIn(
            $model->getKeyName(), $keys
        )->get()->keyBy($model->getKeyName());

	    return collect($results['ids'])->map(function ($hit) use ($models) {
		    if ($models->has($hit)) {
			    return $models[$hit];
		    }
	    })->filter(function ($value) {
		    return (!is_null($value));
	    });
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     *
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits'];
    }

    public function initIndex($model)
    {
        $indexName = $model->searchableAs();

        if (!file_exists($this->tnt->config['storage']."/{$indexName}.index")) {
            $indexer = $this->tnt->createIndex("$indexName.index");
            $indexer->setDatabaseHandle($model->getConnection()->getPdo());
            $indexer->disableOutput = true;
            $indexer->setPrimaryKey($model->getKeyName());
            $fields = implode(', ', array_keys($model->toSearchableArray()));
            $indexer->query("SELECT {$model->getKeyName()}, $fields FROM {$model->getTable()} WHERE {$model->getKeyName()} = {$model->getKey()}");
            $indexer->run();
        }
    }
}

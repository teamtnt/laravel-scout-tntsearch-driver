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
        $this->tnt->selectIndex("{$models->first()->searchableAs()}.index");
        $index = $this->tnt->getIndex();
        $index->setPrimaryKey($models->first()->getKeyName());

        $index->indexBeginTransaction();
        $models->each(function ($model) use ($index) {
            if ($model->getKey()) {
                $index->update($model->getKey(), $model->toSearchableArray());
            } else {
                $index->insert($model->toSearchableArray());
            }
        });
        $index->indexEndTransaction();
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
            $index->delete($model->getKey());
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
        $results = $this->performSearch($builder);

        if ($builder->limit) {
            $results['hits'] = $builder->limit;
        }

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
        $limit = $builder->limit ?: 10000;
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

        $keys   = collect($results['ids'])->values()->all();
        $models = $model->whereIn(
            $model->getQualifiedKeyName(), $keys
        )->get()->keyBy($model->getKeyName());

        return collect($results['ids'])->map(function ($hit) use ($models) {
            return $models->has($hit) ? $models[$hit] : null;
        })->filter(function ($model) {
            return !is_null($model);
        });
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['ids'])->values();
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
            $indexer->setPrimaryKey($model->getKeyName());
        }
    }
}

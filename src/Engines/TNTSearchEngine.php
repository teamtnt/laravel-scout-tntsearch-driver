<?php

namespace TeamTNT\Scout\Engines;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;

class TNTSearchEngine extends Engine
{
    /**
     * @var TNTSearch
     */
    protected $tnt;

    /**
     * @var Builder
     */
    protected $builder;

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
            $array = $model->toSearchableArray();

            if (empty($array)) {
                return;
            }

            if ($model->getKey()) {
                $index->update($model->getKey(), $array);
            } else {
                $index->insert($array);
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
        try {
            return $this->performSearch($builder);
        } catch (IndexNotFoundException $e) {
            $this->initIndex($builder->model);
        }
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

        $searchResults = $results['ids'];

        $qualifiedKeyName = $builder->model->getQualifiedKeyName();  // tableName.id
        $subQualifiedKeyName = 'sub.' . $builder->model->getKeyName(); // sub.id
    
        $sub = $this->getBuilder($builder->model)->whereIn(
            $qualifiedKeyName, $searchResults
        ); // sub query for left join

        /*
         * The search index results ($results['ids']) need to be compared against our query
         * that contains the constraints.
         * 
         * To get the correct results and counts for the pagination, we remove those ids
         * from the search index results that were found by the search but are not part of
         * the query ($sub) that is constrained.
         * 
         * This is achieved with self joining the constrained query as subquery and selecting
         * the ids which are not matching to anything (i.e., is null).
         * 
         * The constraints usually remove only a small amount of results, which is why the non
         * matching results are looked up and removed, instead of returning a collection with
         * all the valid results.
         */
        $discardIds = $builder->model->newQuery()
            ->select($qualifiedKeyName)
            ->leftJoinSub($sub->getQuery(), 'sub', $subQualifiedKeyName, '=', $qualifiedKeyName)
            ->whereIn($qualifiedKeyName, $searchResults)
            ->whereNull($subQualifiedKeyName)
            ->pluck($builder->model->getKeyName());

        // returns values of $results['ids'] that are not part of $discardIds
        $filtered = collect($results['ids'])->diff($discardIds);

        $results['hits'] = $filtered->count();

        $chunks = array_chunk($filtered->toArray(), $perPage);
        
        if (empty($chunks)) {
            return $results;
        }

        if (array_key_exists($page - 1, $chunks)) {
            $results['ids'] = $chunks[$page - 1];
        } else {
            $results['ids'] = end($chunks);
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
    protected function performSearch(Builder $builder, array $options = [])
    {
        $index = $builder->index ?: $builder->model->searchableAs();
        $limit = $builder->limit ?: 10000;
        $this->tnt->selectIndex("{$index}.index");

        $this->builder = $builder;

        if (isset($builder->model->asYouType)) {
            $this->tnt->asYouType = $builder->model->asYouType;
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->tnt,
                $builder->query,
                $options
            );
        }
        if (isset($this->tnt->config['searchBoolean']) ? $this->tnt->config['searchBoolean'] : false) {
            return $this->tnt->searchBoolean($builder->query, $limit);
        } else {
            return $this->tnt->search($builder->query, $limit);
        }
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

        $builder = $this->getBuilder($model);

        $models = $builder->whereIn(
            $model->getQualifiedKeyName(), $keys
        )->get()->keyBy($model->getKeyName());

        return collect($results['ids'])->map(function ($hit) use ($models) {
            if (isset($models[$hit])) {
                return $models[$hit];
            }
        })->filter()->values();
    }

    /**
     * Return query builder either from given constraints, or as
     * new query. Add where statements to builder when given.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return Builder
     */
    public function getBuilder($model)
    {
        // get query as given constraint or create a new query
        $builder = isset($this->builder->constraints) ? $this->builder->constraints : $model->newQuery();

        // iterate over given where clauses
        return collect($this->builder->wheres)->map(function ($value, $key) {
            // for reduce function combine key and value into array
            return [$key, $value];
        })->reduce(function ($builder, $where) {
            // separate key, value again
            list($key, $value) = $where;
            return $builder->where($key, $value);
        }, $builder);
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

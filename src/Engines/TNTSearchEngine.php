<?php

namespace TeamTNT\Scout\Engines;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use TeamTNT\Scout\Events\SearchPerformed;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchEngine extends Engine
{

    private $filters;
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

    public function getTNT()
    {
        return $this->tnt;
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

        $filtered = $this->discardIdsFromResultSetByConstraints($builder, $results['ids']);

        $results['hits'] = $filtered->count();

        $chunks = array_chunk($filtered->toArray(), $perPage);

        if (empty($chunks)) {
            return $results;
        }

        if (array_key_exists($page - 1, $chunks)) {
            $results['ids'] = $chunks[$page - 1];
        } else {
            $results['ids'] = [];
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

        $builder->query = $this->applyFilters('query_expansion', $builder->query, get_class($builder->model));

        if (isset($this->tnt->config['searchBoolean']) ? $this->tnt->config['searchBoolean'] : false) {
            $res = $this->tnt->searchBoolean($builder->query, $limit);
            event(new SearchPerformed($builder, $res, true));
            return $res;

        } else {
            $res = $this->tnt->search($builder->query, $limit);
            event(new SearchPerformed($builder, $res));
            return $res;
        }
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param mixed                               $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (empty($results['ids'])) {
            return Collection::make();
        }

        $keys = collect($results['ids'])->values()->all();

        $builder = $this->getBuilder($model);

        if ($this->builder->queryCallback) {
            call_user_func($this->builder->queryCallback, $builder);
        }

        $models = $builder->whereIn(
            $model->getQualifiedKeyName(), $keys
        )->get()->keyBy($model->getKeyName());

        // sort models by user choice
        if (!empty($this->builder->orders)) {
            return $models->values();
        }

        // sort models by tnt search result set
        return $model->newCollection($results['ids'])->map(function ($hit) use ($models) {
            if (isset($models[$hit])) {
                return $models[$hit];
            }
        })->filter()->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param mixed                               $results
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (empty($results['ids'])) {
            return LazyCollection::make();
        }

        $keys = collect($results['ids'])->values()->all();

        $builder = $this->getBuilder($model);

        if ($this->builder->queryCallback) {
            call_user_func($this->builder->queryCallback, $builder);
        }

        $models = $builder->whereIn(
            $model->getQualifiedKeyName(), $keys
        )->get()->keyBy($model->getKeyName());

        // sort models by user choice
        if (!empty($this->builder->orders)) {
            return $models->values();
        }

        // sort models by tnt search result set
        return $model->newCollection($results['ids'])->map(function ($hit) use ($models) {
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

        $builder = $this->handleSoftDeletes($builder, $model);

        $builder = $this->applyWheres($builder);

        $builder = $this->applyOrders($builder);

        return $builder;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        if (empty($results['ids'])) {
            return collect();
        }

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

    /**
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
    private function discardIdsFromResultSetByConstraints($builder, $searchResults)
    {
        $qualifiedKeyName    = $builder->model->getQualifiedKeyName(); // tableName.id
        $subQualifiedKeyName = 'sub.'.$builder->model->getKeyName(); // sub.id

        $sub = $this->getBuilder($builder->model)->whereIn(
            $qualifiedKeyName, $searchResults
        ); // sub query for left join

        $discardIds = $builder->model->newQuery()
            ->select($qualifiedKeyName)
            ->leftJoin(DB::raw('('.$sub->getQuery()->toSql().') as '.$builder->model->getConnection()->getTablePrefix().'sub'), $subQualifiedKeyName, '=', $qualifiedKeyName)
            ->addBinding($sub->getQuery()->getBindings(), 'join')
            ->whereIn($qualifiedKeyName, $searchResults)
            ->whereNull($subQualifiedKeyName)
            ->pluck($builder->model->getKeyName());

        // returns values of $results['ids'] that are not part of $discardIds
        return collect($searchResults)->diff($discardIds);
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Determine if soft delete is active and depending on state return the
     * appropriate builder.
     *
     * @param  Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Builder
     */
    private function handleSoftDeletes($builder, $model)
    {
        // remove where statement for __soft_deleted when soft delete is not active
        // does not show soft deleted items when trait is attached to model and
        // config('scout.soft_delete') is false
        if (!$this->usesSoftDelete($model) || !config('scout.soft_delete', true)) {
            unset($this->builder->wheres['__soft_deleted']);
            return $builder;
        }

        /**
         * Use standard behaviour of Laravel Scout builder class to support soft deletes.
         *
         * When no __soft_deleted statement is given return all entries
         */
        if (!array_key_exists('__soft_deleted', $this->builder->wheres)) {
            return $builder->withTrashed();
        }

        /**
         * When __soft_deleted is 1 then return only soft deleted entries
         */
        if ($this->builder->wheres['__soft_deleted']) {
            $builder = $builder->onlyTrashed();
        }

        /**
         * Returns all undeleted entries, default behaviour
         */
        unset($this->builder->wheres['__soft_deleted']);
        return $builder;
    }

    /**
     * Apply where statements as constraints to the query builder.
     *
     * @param Builder $builder
     * @return \Illuminate\Support\Collection
     */
    private function applyWheres($builder)
    {
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
     * Apply order by statements as constraints to the query builder.
     *
     * @param Builder $builder
     * @return \Illuminate\Support\Collection
     */
    private function applyOrders($builder)
    {
        //iterate over given orderBy clauses - should be only one
        return collect($this->builder->orders)->map(function ($value, $key) {
            // for reduce function combine key and value into array
            return [$value["column"], $value["direction"]];
        })->reduce(function ($builder, $orderBy) {
            // separate key, value again
            list($column, $direction) = $orderBy;
            return $builder->orderBy($column, $direction);
        }, $builder);
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $indexName   = $model->searchableAs();
        $pathToIndex = $this->tnt->config['storage']."/{$indexName}.index";
        if (file_exists($pathToIndex)) {
            unlink($pathToIndex);
        }
    }


    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     *
     * @throws \Exception
     */
    public function createIndex($name, array $options = [])
    {
        throw new Exception('TNT indexes are created automatically upon adding objects.');
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex($name)
    {
        throw new Exception(sprintf('TNT indexes cannot reliably be removed. Please manually remove the file in %s/%s.index', config('scout.tntsearch.storage'), $name));
    }

    /**
     * Adds a filter
     *
     * @param  string
     * @param  callback
     * @return void
     */
    public function addFilter($name, $callback)
    {
        if (!is_callable($callback, true)) {
            throw new InvalidArgumentException(sprintf('Filter is an invalid callback: %s.', print_r($callback, true)));
        }
        $this->filters[$name][] = $callback;
    }

    /**
     * Returns an array of filters
     *
     * @param  string
     * @return array
     */
    public function getFilters($name)
    {
        return isset($this->filters[$name]) ? $this->filters[$name] : [];
    }

    /**
     * Returns a string on which a filter is applied
     *
     * @param  string
     * @param  string
     * @return string
     */
    public function applyFilters($name, $result, $model)
    {
        foreach ($this->getFilters($name) as $callback) {
            // prevent fatal errors, do your own warning or
            // exception here as you need it.
            if (!is_callable($callback)) {
                continue;
            }

            $result = call_user_func($callback, $result, $model);
        }
        return $result;
    }
}

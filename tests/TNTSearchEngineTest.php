<?php

use Illuminate\Database\Eloquent\Collection;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use TeamTNT\Scout\Engines\TNTSearchEngine;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchEngineTest extends TestCase
{

    protected function tearDown(): void
    {
        m::close();
    }

    public function testApplyingFilters()
    {
        $tnt    = new TNTSearch;
        $engine = new TeamTNT\Scout\Engines\TNTSearchEngine($tnt);

        $engine->addFilter("query_expansion", function ($query, $model) {
            if ($query == "test" && $model == "TeamTNT\TNTSearch\TNTSearch") {
                return "modified-".$query;
            }
            return $query;

        });

        $query  = $engine->applyFilters('query_expansion', "test", TNTSearch::class);
        $query2 = $engine->applyFilters('query_expansion', "test", Collection::class);
        $query3 = $engine->applyFilters('query_expansion', "test2", TNTSearch::class);

        $this->assertTrue($query == "modified-test");
        $this->assertTrue($query2 == "test");
        $this->assertTrue($query3 == "test2");
    }

    public function testAppliesScout11FormatWheres()
    {
        $engine = new TNTSearchEngine(new TNTSearch);

        $query = m::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('where')->once()->with('active', '=', 1)->andReturnSelf();
        $query->shouldReceive('where')->once()->with('age', '>', 30)->andReturnSelf();

        $builder              = new stdClass;
        $builder->constraints = $query;
        $builder->wheres      = [
            ['field' => 'active', 'operator' => '=', 'value' => 1],
            ['field' => 'age', 'operator' => '>', 'value' => 30],
        ];
        $builder->orders = [];

        $this->setProtected($engine, 'builder', $builder);

        $this->assertSame($query, $engine->getBuilder(new TNTSearchEngineTestModel));
    }

    public function testAppliesScout10FormatWheres()
    {
        $engine = new TNTSearchEngine(new TNTSearch);

        $query = m::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('where')->once()->with('active', '=', 1)->andReturnSelf();
        $query->shouldReceive('where')->once()->with('age', '=', 30)->andReturnSelf();

        $builder              = new stdClass;
        $builder->constraints = $query;
        $builder->wheres      = ['active' => 1, 'age' => 30];
        $builder->orders      = [];

        $this->setProtected($engine, 'builder', $builder);

        $this->assertSame($query, $engine->getBuilder(new TNTSearchEngineTestModel));
    }

    public function testAppliesWhereInAndWhereNotIn()
    {
        $engine = new TNTSearchEngine(new TNTSearch);

        $query = m::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('whereIn')->once()->with('country', ['US', 'BR'])->andReturnSelf();
        $query->shouldReceive('whereNotIn')->once()->with('status', [0])->andReturnSelf();

        $builder                = new stdClass;
        $builder->constraints   = $query;
        $builder->wheres        = [];
        $builder->whereIns      = ['country' => ['US', 'BR']];
        $builder->whereNotIns   = ['status' => [0]];
        $builder->orders        = [];

        $this->setProtected($engine, 'builder', $builder);

        $this->assertSame($query, $engine->getBuilder(new TNTSearchEngineTestModel));
    }

    public function testWhereInAndWhereNotInAreOptionalForOlderScout()
    {
        // Scout <9.4 has no whereIns; Scout <10 has no whereNotIns. Missing
        // properties must not error and must apply no constraint.
        $engine = new TNTSearchEngine(new TNTSearch);

        $query = m::mock('Illuminate\Database\Eloquent\Builder');
        $query->shouldReceive('whereIn')->never();
        $query->shouldReceive('whereNotIn')->never();

        $builder              = new stdClass;
        $builder->constraints = $query;
        $builder->wheres      = [];
        $builder->orders      = [];
        // no whereIns / whereNotIns properties at all

        $this->setProtected($engine, 'builder', $builder);

        $this->assertSame($query, $engine->getBuilder(new TNTSearchEngineTestModel));
    }

    public function testFindSoftDeleteWhereSupportsBothFormats()
    {
        $engine = new TNTSearchEngine(new TNTSearch);

        // Scout 11+ format
        $builder         = new stdClass;
        $builder->wheres = [
            ['field' => 'active', 'operator' => '=', 'value' => 1],
            ['field' => '__soft_deleted', 'operator' => '=', 'value' => 1],
        ];
        $this->setProtected($engine, 'builder', $builder);
        $this->assertSame(
            ['field' => '__soft_deleted', 'value' => 1],
            $this->callProtected($engine, 'findSoftDeleteWhere')
        );

        // Scout <=10 format
        $builder         = new stdClass;
        $builder->wheres = ['active' => 1, '__soft_deleted' => 0];
        $this->setProtected($engine, 'builder', $builder);
        $this->assertSame(
            ['field' => '__soft_deleted', 'value' => 0],
            $this->callProtected($engine, 'findSoftDeleteWhere')
        );

        // Absent
        $builder         = new stdClass;
        $builder->wheres = [['field' => 'active', 'operator' => '=', 'value' => 1]];
        $this->setProtected($engine, 'builder', $builder);
        $this->assertNull($this->callProtected($engine, 'findSoftDeleteWhere'));
    }

    public function testRemoveSoftDeleteWherePreservesOtherClauses()
    {
        $engine = new TNTSearchEngine(new TNTSearch);

        // Scout 11+ format
        $builder         = new stdClass;
        $builder->wheres = [
            ['field' => 'active', 'operator' => '=', 'value' => 1],
            ['field' => '__soft_deleted', 'operator' => '=', 'value' => 0],
        ];
        $this->setProtected($engine, 'builder', $builder);
        $this->callProtected($engine, 'removeSoftDeleteWhere');
        $this->assertSame(
            [['field' => 'active', 'operator' => '=', 'value' => 1]],
            array_values($builder->wheres)
        );

        // Scout <=10 format
        $builder         = new stdClass;
        $builder->wheres = ['active' => 1, '__soft_deleted' => 0];
        $this->setProtected($engine, 'builder', $builder);
        $this->callProtected($engine, 'removeSoftDeleteWhere');
        $this->assertSame(['active' => 1], $builder->wheres);
    }

    private function setProtected($object, $property, $value)
    {
        $reflection = new ReflectionProperty(get_class($object), $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    private function callProtected($object, $method, array $args = [])
    {
        $reflection = new ReflectionMethod(get_class($object), $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $args);
    }
}

class TNTSearchEngineTestModel
{
    public $searchable = ['title'];

    public function searchableAs()
    {
        return 'table';
    }

    public function getTable()
    {
        return 'table';
    }

    public function getTablePrefix()
    {
        return "";
    }

    public function getKey()
    {
        return 1;
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function toSearchableArray()
    {
        return ['id' => 1];
    }

    public function getConnection()
    {
        $connection = Mockery::mock('Illuminate\Database\MySqlConnection');
        $connection->shouldReceive('getPdo')->andReturn(Mockery::mock('PDO'));

        return $connection;
    }
}

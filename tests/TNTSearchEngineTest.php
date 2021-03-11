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

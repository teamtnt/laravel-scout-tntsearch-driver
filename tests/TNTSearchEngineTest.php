<?php

use Illuminate\Database\Eloquent\Collection;
use TeamTNT\Scout\Engines\TNTSearchEngine;

class TNTSearchEngineTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function test_update_adds_objects_to_index()
    {
        $client = Mockery::mock('TeamTNT\TNTSearch\TNTSearch');
        $client->shouldReceive('createIndex')
            ->with('table.index')
            ->andReturn($index = Mockery::mock('TeamTNT\TNTSearch\Indexer\TNTIndexer'));
        $index->shouldReceive('setDatabaseHandle');
        $index->shouldReceive('setPrimaryKey');
        $index->shouldReceive('query');
        $index->shouldReceive('run');

        $client->shouldReceive('selectIndex');
        $client->shouldReceive('getIndex')
            ->andReturn($index);

        $index->shouldReceive('indexBeginTransaction');
        $index->shouldReceive('update');
        $index->shouldReceive('indexEndTransaction');

        $geoClient = Mockery::mock('TeamTNT\TNTSearch\TNTGeoSearch');

        $config = [
            'storage'  => '/',
            'fuzziness' => false,
            'fuzzy' => [
                'prefix_length' => 2,
                'max_expansions' => 50,
                'distance' => 2
            ],
            'asYouType' => false,
            'searchBoolean' => false,
        ];

        $geoClient->shouldReceive('getIndex')
            ->andReturn($geoIndex = Mockery::mock('TeamTNT\TNTSearch\Indexer\TNTGeoIndexer'))
            ->andSet('config', $config);
        $geoIndex->shouldReceive('loadConfig');
        $geoIndex->shouldReceive('createIndex')
            ->with('table.geoindex');
        $geoIndex->shouldReceive('setDatabaseHandle');
        $geoIndex->shouldReceive('setPrimaryKey');
        $geoClient->shouldReceive('selectIndex');
        $geoIndex->shouldReceive('indexBeginTransaction');
        $geoIndex->shouldReceive('prepareAndExecuteStatement');
        $geoIndex->shouldReceive('insert');
        $geoIndex->shouldReceive('indexEndTransaction');


        $engine = new TNTSearchEngine($client, $geoClient);
        $engine->update(Collection::make([new TNTSearchEngineTestModel()]));
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

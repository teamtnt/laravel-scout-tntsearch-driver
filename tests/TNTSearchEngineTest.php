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
        $index->shouldReceive('query');
        $index->shouldReceive('run');

        $client->shouldReceive('selectIndex');
        $client->shouldReceive('getIndex')
            ->andReturn($index);

        $index->shouldReceive('update');

        $engine = new TNTSearchEngine($client);
        $engine->update(Collection::make([new TNTSearchEngineTestModel()]));
    }
}

class TNTSearchEngineTestModel
{
    public function searchableAs()
    {
        return 'table';
    }

    public function getKey()
    {
        return 1;
    }

    public function toSearchableArray()
    {
        return ['id' => 1];
    }
}

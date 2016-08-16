<?php


class AlgoliaEngineTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        Mockery::close();
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

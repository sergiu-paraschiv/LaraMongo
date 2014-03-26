<?php

use Mockery as m;

class DatabaseQueryBuilderTest extends PHPUnit_Framework_TestCase {

    public function tearDown()
    {
        m::close();
    }

    protected function getBuilder()
    {
        $grammar = new Illuminate\Database\Query\Grammars\Grammar;
        $processor = m::mock('Illuminate\Database\Query\Processors\Processor');
        return new Builder(m::mock('Illuminate\Database\ConnectionInterface'), $grammar, $processor);
    }
}

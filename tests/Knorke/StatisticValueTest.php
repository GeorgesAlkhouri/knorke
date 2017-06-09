<?php

namespace Tests\Knorke;

use Knorke\DataBlank;
use Knorke\InMemoryStore;
use Knorke\StatisticValue;
use Saft\Rdf\BlankNodeImpl;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\NodeUtils;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class StatisticValueTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new StatisticValue(
            $this->store,
            $this->commonNamespaces,
            $this->rdfHelpers,
            array($this->testGraph)
        );

        $this->store->dropGraph($this->testGraph);
        $this->store->createGraph($this->testGraph);

        $this->commonNamespaces->add('stat', 'http://stat/');
    }

    /*
     * Tests for compute
     */

    public function testComputeTestEmptyStartMapping()
    {
        $this->expectException('Knorke\Exception\KnorkeException');

        $this->fixture->setStartMapping(array());

        $this->fixture->compute(array('foo:1'));
    }

    public function testComputeTestEmptyValuesParameter()
    {
        $this->expectException('Knorke\Exception\KnorkeException');

        $this->fixture->compute(array());
    }

    public function testComputeTestNonDependingValueHasNoValue()
    {
        $this->expectException('Knorke\Exception\KnorkeException');

        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .

            stat:1 rdf:type kno:StatisticValue .

            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:1]*2"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        $this->fixture->setStartMapping(array());

        $this->fixture->compute(array('stat:2'));
    }

    public function testComputeTestWithSimpleRule()
    {
        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .
            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:1]*2" ;
                    kno:_1 "+4"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        // tell him, what you want to compute
        $this->fixture->setStartMapping(array(
            'stat:1' => 2,
        ));

        $this->assertEquals(
            array(
                'stat:1' => 2,
                'stat:2' => 8, // <----------,
            ),                 //            |
            $this->fixture->compute(array('stat:2'))
        );
    }

    // test that missing values gets computated before usage
    public function testComputeMissingValueComputation()
    {
        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .
            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:3]+1" ;
                    kno:_1 "+2"
                ] .
            stat:3 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:1]*5"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        /*
         * check with non-prefixed keys in mapping
         */
        $this->fixture->setStartMapping(array(
            'stat:1' => 2
        ));
        $this->assertEquals(
            array(
                'stat:1' => 2,
                'stat:2' => 13, // <--------,
                'stat:3' => 10, // <--------|-----------,
            ),                  //          |           |
            $this->fixture->compute(array('stat:2', 'stat:3'))
        );
    }

    // test handling of if clauses
    public function testComputeIfClause()
    {
        $this->commonNamespaces->add('stat', 'http://stat/');

        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .
            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "IF([stat:1]>30, 1, 0)"
                ] .

            stat:3 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "IF([stat:2]<1, 1, 0)"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        /*
         * check for if option
         */
        $this->fixture->setStartMapping(array(
            'stat:1' => 31
        ));

        $this->assertEquals(
            array(
                'stat:1' => 31,
                'stat:2' => 1,
                'stat:3' => 0,
            ),
            $this->fixture->compute(array('stat:2', 'stat:3'))
        );

        /*
         * check for else option
         */
        $this->fixture->setStartMapping(array(
            'stat:1' => 20
        ));

        $this->assertEquals(
            array(
                'stat:1' => 20,
                'stat:2' => 0,
                'stat:3' => 1,
            ),
            $this->fixture->compute(array('stat:2', 'stat:3'))
        );
    }

    // test MAX(result, xxx) rule
    public function testComputeMax()
    {
        $this->commonNamespaces->add('stat', 'http://stat/');

        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .
            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:1]*2" ;
                    kno:_1 "MAX(result,2)"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        // check for result
        $this->fixture->setStartMapping(array('stat:1' => 2));

        $this->assertEquals(
            array(
                'stat:1' => 2,
                'stat:2' => 4
            ),
            $this->fixture->compute(array('stat:2'))
        );

        // check for alternative
        $this->fixture->setStartMapping(array('stat:1' => 0.4));

        $this->assertEquals(
            array(
                'stat:1' => 0.4,
                'stat:2' => 2
            ),
            $this->fixture->compute(array('stat:2'))
        );
    }

    // check how compute reacts on a missing mapping
    public function testComputeMissingMapping()
    {
        $this->expectException('Knorke\Exception\KnorkeException');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:1'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
        ));

        $this->fixture->setStartMapping(array());

        $this->fixture->compute(array());
    }

    // test ROUNDUP rule
    public function testComputeRoundUp()
    {
        $this->commonNamespaces->add('stat', 'http://stat/');

        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .
            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:1]*2" ;
                    kno:_1 "ROUNDUP"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        // round up with <0.5
        $this->fixture->setStartMapping(array('stat:1' => 0.2));

        $this->assertEquals(
            array(
                'stat:1' => 0.2,
                'stat:2' => 1
            ),
            $this->fixture->compute(array('stat:2'))
        );

        // round up with >0.5
        $this->fixture->setStartMapping(array('stat:1' => 0.6));

        $this->assertEquals(
            array(
                'stat:1' => 0.6,
                'stat:2' => 2
            ),
            $this->fixture->compute(array('stat:2'))
        );
    }

    // test usage of a foreign value
    public function testComputeUseValue()
    {
        $this->commonNamespaces->add('stat', 'http://stat/');

        $this->importTurtle('
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdf: <'. $this->commonNamespaces->getUri('rdf') .'> .
            @prefix stat: <http://stat/> .
            stat:2 rdf:type kno:StatisticValue ;
                kno:computation-order [
                    kno:_0 "[stat:1]"
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        $this->fixture->setStartMapping(array('stat:1' => 0.2));

        $this->assertEquals(
            array(
                'stat:1' => 0.2,
                'stat:2' => 0.2
            ),
            $this->fixture->compute(array('stat:2'))
        );
    }

    /*
     * Tests for compute Value
     */

    public function testComputeValue()
    {
        $this->fixture->setStartMapping(array());

        $this->assertEquals('2017-01-10', $this->fixture->computeValue('2017-01-05', '+', 5));
        $this->assertEquals('2017-01-01', $this->fixture->computeValue('2017-01-06', '-', 5));
        $this->assertEquals(2,            $this->fixture->computeValue('2017-01-06', '-', '2017-01-04'));
    }

    public function testComputeValueInvalidSecondParameter()
    {
        $this->fixture->setStartMapping(array());

        $this->assertNull($this->fixture->computeValue('2017-01-01 00:00:00', '*', 1));
        $this->assertNull($this->fixture->computeValue('2017-01-01 00:00:00', '/', 1));
    }

    public function testComputeValueInvalidThirdParameter()
    {
        $this->fixture->setStartMapping(array());

        $this->assertNull($this->fixture->computeValue('2017-01-01 00:00:00', '+', '2017-01-01 00:00:00'));
        $this->assertNull($this->fixture->computeValue('2017-01-01 00:00:00', '/', '2017-01-01 00:00:00'));
        $this->assertNull($this->fixture->computeValue('2017-01-01 00:00:00', '*', '2017-01-01 00:00:00'));
    }
}

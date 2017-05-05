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
            $this->rdfHelpers
        );
    }

    /*
     * Tests for compute
     */

    public function testCompute()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            // stat:1
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:1'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:1'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:2]*2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_1'),
                $this->nodeFactory->createLiteral('+4.5')
            ),
            // stat:2
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Statistic Value 2')
            ),
            // stat:date
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:date'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:date'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid2'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:startdate]-4')
            ),
            // stat:days
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:days'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:days'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid3')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid3'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:startdate]-stat:date')
            ),
        ));

        /*
         * check with non-prefixed keys in mapping
         */
        $this->fixture->setMapping(array(
            'http://statValue/2' => 2,
            'http://statValue/startdate' => '2017-01-05'
        ));
        $this->assertEquals(
            array(
                'http://statValue/1' => 8.5,
                'http://statValue/2' => 2,
                'http://statValue/date' => '2017-01-01',
                'http://statValue/days' => 4.0,
                'http://statValue/startdate' => '2017-01-05'
            ),
            $this->fixture->compute()
        );

        /*
         * check with prefixed keys in mapping
         */
        $this->fixture->setMapping(array(
            'stat:2' => 2,
            'http://statValue/startdate' => '2017-01-05'
        ));
        $this->assertEquals(
            array(
                'http://statValue/1' => 8.5,
                'http://statValue/2' => 2,
                'http://statValue/date' => '2017-01-01',
                'http://statValue/days' => 4.0,
                'http://statValue/startdate' => '2017-01-05'
            ),
            $this->fixture->compute()
        );
    }

    // if static value information is described using exented URIs, check prefixed version
    public function testComputeCheckForPrefixedAndUnprefixedUri()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/1'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:1]*2')
            ),
        ));

        $this->fixture->setMapping(array(
            'http://statValue/1' => 5
        ));

        $this->assertEquals(
            array(
                'http://statValue/1' => 5,
                'http://statValue/2' => 10
            ),
            $this->fixture->compute()
        );
    }

    // test handling of if clauses
    public function testComputeIfClause()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('IF([stat:1]>30, 1, 0)')
            ),
        ));

        // check for if option
        $this->fixture->setMapping(array('http://statValue/1' => 31));

        $this->assertEquals(
            array(
                'http://statValue/1' => 31,
                'http://statValue/2' => 1
            ),
            $this->fixture->compute()
        );

        // check for else option
        $this->fixture->setMapping(array('http://statValue/1' => 20));

        $this->assertEquals(
            array(
                'http://statValue/1' => 20,
                'http://statValue/2' => 0
            ),
            $this->fixture->compute()
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

        $this->fixture->setMapping(array());

        $this->fixture->compute();
    }

    public function testComputeMax()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:1]*2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_1'),
                $this->nodeFactory->createLiteral('MAX(result,2)')
            ),
        ));

        // check for result
        $this->fixture->setMapping(array('http://statValue/1' => 2));

        $this->assertEquals(
            array(
                'http://statValue/1' => 2,
                'http://statValue/2' => 4
            ),
            $this->fixture->compute()
        );

        // check for alternative
        $this->fixture->setMapping(array('http://statValue/1' => 0.4));

        $this->assertEquals(
            array(
                'http://statValue/1' => 0.4,
                'http://statValue/2' => 2
            ),
            $this->fixture->compute()
        );
    }

    public function testComputeRoundUp()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:1]*2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_1'),
                $this->nodeFactory->createLiteral('ROUNDUP')
            ),
        ));

        // round up with <0.5
        $this->fixture->setMapping(array('http://statValue/1' => 0.2));

        $this->assertEquals(
            array(
                'http://statValue/1' => 0.2,
                'http://statValue/2' => 1
            ),
            $this->fixture->compute()
        );

        // round up with >0.5
        $this->fixture->setMapping(array('http://statValue/1' => 0.4));

        $this->assertEquals(
            array(
                'http://statValue/1' => 0.4,
                'http://statValue/2' => 1
            ),
            $this->fixture->compute()
        );
    }

    public function testComputeUseValue()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://statValue/2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:1]')
            )
        ));

        $this->fixture->setMapping(array('http://statValue/1' => 0.2));

        $this->assertEquals(
            array(
                'http://statValue/1' => 0.2,
                'http://statValue/2' => 0.2
            ),
            $this->fixture->compute()
        );
    }

    /*
     * Tests for compute Value
     */

    public function testComputeValue()
    {
        $this->fixture->setMapping(array());

        $this->assertEquals('2017-01-10', $this->fixture->computeValue('2017-01-05', '+', 5));
        $this->assertEquals('2017-01-01', $this->fixture->computeValue('2017-01-06', '-', 5));
        $this->assertEquals(2,            $this->fixture->computeValue('2017-01-06', '-', '2017-01-04'));
    }

    /*
     * Tests for computeValue
     */

    public function testExecuteComputationOrderWithDoubleValuesValueIsNumber()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $mapping = array('http://statValue/1' => 3);
        $this->fixture->setMapping($mapping);

        $this->assertEquals(
            6,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]*2'), $mapping, array())
        );
        $this->assertEquals(
            5,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]+2'), $mapping, array())
        );
        $this->assertEquals(
            1,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]-2'), $mapping, array())
        );
        $this->assertEquals(
            1.5,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]/2'), $mapping, array())
        );
    }

    public function testExecuteComputationOrderWithDoubleValuesValueIsUri()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $mapping = array('http://statValue/1' => 3, 'http://statValue/2' => 3);

        $this->fixture->setMapping($mapping);

        // multiple
        $this->assertEquals(
            9, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]*stat:2'), $mapping, array())
        );
        $this->assertEquals(
            9, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]*http://statValue/2'), $mapping, array())
        );

        // plus
        $this->assertEquals(
            6, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]+stat:2'), $mapping, array())
        );
        $this->assertEquals(
            6, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]+http://statValue/2'), $mapping, array())
        );

        // minus
        $this->assertEquals(
            0, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]-stat:2'), $mapping, array())
        );
        $this->assertEquals(
            0, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]-http://statValue/2'), $mapping, array())
        );

        // division
        $this->assertEquals(
            1, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]/stat:2'), $mapping, array())
        );
        $this->assertEquals(
            1, $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]/http://statValue/2'), $mapping, array())
        );
    }

    // check how the computation reacts if it has to use an uncomputed value in a computation
    public function testExecuteComputationOrderWithNotYetComputedValue()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid2'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:3]*2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:3'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:3'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid3')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid3'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:1]+4')
            ),
        ));

        $this->fixture->setMapping(array(/* doesnt matter */));
        $this->assertEquals(
            14,
            $this->fixture->executeComputationOrder(
                array(
                    'kno:_0' => '[stat:3]*2'
                ),
                array('http://statValue/1' => 3),
                array(
                    'http://statValue/2' => array(
                        'kno:_0' => '[stat:3]*2'
                    ),
                    'http://statValue/3' => array(
                        'kno:_0' => '[stat:1]+4'
                    ),
                )
            )
        );
    }

    /*
     * Tests for getComputationOrderFor
     */

    public function testGetComputationOrderFor()
    {
        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:1'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:2]*2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('stat:2'),
                $this->nodeFactory->createNamedNode('kno:computationOrder'),
                $this->nodeFactory->createBlankNode('genid2')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid2'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createLiteral('[stat:0]+4')
            ),
        ));

        $this->fixture->setMapping(array());

        $this->assertEquals(
            array(
                'kno:_0' => '[stat:0]+4'
            ),
            $this->fixture->getComputationOrderFor(
                'stat:2',
                array(
                    'stat:1' => array(
                        'rdf:type' => 'kno:StatisticValue',
                        'kno:computationOrder' => '_:genId1'
                    ),
                    'stat:2' => array(
                        'rdf:type' => 'kno:StatisticValue',
                        'kno:computationOrder' => '_:genId2'
                    ),
                )
            )
        );
    }
}

<?php

namespace Tests\Knorke;

use Knorke\CommonNamespaces;
use Knorke\DataBlank;
use Knorke\InMemoryStore;
use Knorke\StatisticValue;
use Saft\Rdf\BlankNodeImpl;
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
    protected $nodeUtils;

    public function setUp()
    {
        $this->nodeUtils = new NodeUtils();

        $this->commonNamespaces = new CommonNamespaces();

        $this->store = new InMemoryStore(
            new NodeFactoryImpl($this->nodeUtils),
            new StatementFactoryImpl(),
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl(),
            new CommonNamespaces()
        );
    }

    protected function initFixture(array $mapping = array())
    {
        $this->fixture = new StatisticValue($this->store, $this->commonNamespaces, $mapping);
        return $this->fixture;
    }

    /*
     * Tests for compute
     */

    public function testCompute()
    {
        $this->store->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:2]*2')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_1'),
                new LiteralImpl($this->nodeUtils, '+4.5')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Statistic Value 2')
            ),
        ));

        $this->commonNamespaces->add('stat', 'http://statValue/');

        // setup mapping for non-depending values
        $this->initFixture(array(
            'stat:2' => 2
        ));

        $this->assertEquals(
            array(
                'stat:1' => 8.5,
                'stat:2' => 2
            ),
            $this->fixture->compute()
        );
    }

    // check how compute reacts on a missing mapping
    public function testComputeMissingMapping()
    {
        $this->setExpectedException('Knorke\Exception\KnorkeException');

        $this->store->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
        ));

        $this->initFixture(array());

        $this->fixture->compute();
    }

    // if static value information is described using exented URIs, check prefixed version
    public function testComputeCheckForPrefixedAndUnprefixedUri()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
        ));

        $this->initFixture(array(
            'stat:1' => 5
        ));

        $this->assertEquals(
            array(
                'stat:1' => 5
            ),
            $this->fixture->compute()
        );
    }

    /*
     * Tests for compute Value
     */

    public function testComputeValue()
    {
        $this->initFixture(array());

        $this->assertEquals('2017-01-10', $this->fixture->computeValue('2017-01-05', '+', 5));
        $this->assertEquals('2017-01-01', $this->fixture->computeValue('2017-01-06', '-', 5));
    }

    /*
     * Tests for computeValue
     */

    public function testExecuteComputationOrderWithDoubleValuesValueIsNumber()
    {
        $mapping = array('stat:1' => 3);
        $this->initFixture($mapping);

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
        $mapping = array('stat:1' => 3, 'stat:2' => 3);
        $this->initFixture($mapping);

        $this->assertEquals(
            9,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]*stat:2'), $mapping, array())
        );
        $this->assertEquals(
            6,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]+stat:2'), $mapping, array())
        );
        $this->assertEquals(
            0,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]-stat:2'), $mapping, array())
        );
        $this->assertEquals(
            1,
            $this->fixture->executeComputationOrder(array('kno:_0' => '[stat:1]/stat:2'), $mapping, array())
        );
    }

    // check how the computation reacts if it has to use an uncomputed value in a computation
    public function testExecuteComputationOrderWithNotYetComputedValue()
    {
        $this->store->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid2')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:3]*2')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:3'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:3'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid3')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid3'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:1]+4')
            ),
        ));

        $this->initFixture(array(/* doesnt matter */));
        $this->assertEquals(
            14,
            $this->fixture->executeComputationOrder(
                array(
                    'kno:_0' => '[stat:3]*2'
                ),
                array('stat:1' => 3),
                array(
                    'stat:2' => array(
                        'kno:_0' => '[stat:3]*2'
                    ),
                    'stat:3' => array(
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
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:2]*2')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid2')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:0]+4')
            ),
        ));

        $this->initFixture(array());

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

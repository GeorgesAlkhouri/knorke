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
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            // stat:1
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
            // stat:2
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
            // stat:date
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:date'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:date'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid2')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:startdate]-4')
            ),
            // stat:days
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:days'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'stat:days'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid3')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid3'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:startdate]-stat:date')
            ),
        ));

        /*
         * check with non-prefixed keys in mapping
         */
        $this->initFixture(array(
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
        $this->initFixture(array(
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
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:1]*2')
            ),
        ));

        $this->initFixture(array(
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

    public function testComputeMax()
    {
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $this->store->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:1]*2')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_1'),
                new LiteralImpl($this->nodeUtils, 'MAX(result,2)')
            ),
        ));

        // check for result
        $this->initFixture(array('http://statValue/1' => 2));

        $this->assertEquals(
            array(
                'http://statValue/1' => 2,
                'http://statValue/2' => 4
            ),
            $this->fixture->compute()
        );

        // check for alternative
        $this->initFixture(array('http://statValue/1' => 0.4));

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
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:1]*2')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_1'),
                new LiteralImpl($this->nodeUtils, 'ROUNDUP')
            ),
        ));

        // round up with <0.5
        $this->initFixture(array('http://statValue/1' => 0.2));

        $this->assertEquals(
            array(
                'http://statValue/1' => 0.2,
                'http://statValue/2' => 1
            ),
            $this->fixture->compute()
        );

        // round up with >0.5
        $this->initFixture(array('http://statValue/1' => 0.4));

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
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://statValue/2'),
                new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new LiteralImpl($this->nodeUtils, '[stat:1]')
            )
        ));

        $this->initFixture(array('http://statValue/1' => 0.2));

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
        $this->initFixture(array());

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
        $this->commonNamespaces->add('stat', 'http://statValue/');

        $mapping = array('http://statValue/1' => 3, 'http://statValue/2' => 3);

        $this->initFixture($mapping);

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

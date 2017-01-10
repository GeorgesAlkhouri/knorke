<?php

namespace Tests\Knorke;

use Knorke\InMemoryStore;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\NodeUtils;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class InMemoryStoreTest extends UnitTestCase
{
    protected $nodeUtils;

    public function setUp()
    {
        $this->nodeUtils = new NodeUtils();

        $this->fixture = new InMemoryStore(
            new NodeFactoryImpl($this->nodeUtils),
            new StatementFactoryImpl(),
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl()
        );
    }

    public function testSPOQuery()
    {
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'http://p'),
                new NamedNodeImpl($this->nodeUtils, 'http://o')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://p'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://o'),
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        // check for classic SPO
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {?s ?p ?o.}')
        );
    }
}

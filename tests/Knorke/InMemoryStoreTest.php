<?php

namespace Tests\Knorke;

use Knorke\InMemoryStore;
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

    // check super standard queries like ?s ?p ?o, nothing special.
    public function testQuerySPOQuery()
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

    // check ?s ?p ?o with a FILTER on ?p
    public function testQuerySPOQueryWithFilter()
    {
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'http://p'),
                new NamedNodeImpl($this->nodeUtils, 'http://o')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'http://p-not-this'),
                new NamedNodeImpl($this->nodeUtils, 'http://o1')
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
            $this->fixture->query(
                'SELECT *
                WHERE {
                    ?s ?p ?o.
                    FILTER (?p = <http://p> || ?p = <http://p1>)
                }'
            )
        );

        // check for classic SPO (2)
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query(
                'SELECT *
                WHERE {
                    ?s ?p ?o.
                    FILTER (?p = <http://p>)
                }'
            )
        );
    }

    // check queries like ?s ?p ?o. ?s rdf:type foaf:Person.
    public function testQuerySPOWithTypedSQuery()
    {
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person')
            ),
            // has to be ignored
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://p-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://o-to-be-ignore')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Label for s'),
            ),
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'foaf:Person'),
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        // check for classic SPO
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {?s ?p ?o. ?s rdf:type foaf:Person}')
        );
    }
}

<?php

namespace Tests\Knorke;

use Knorke\InMemoryStore;
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

class InMemoryStoreTest extends UnitTestCase
{
    protected $commonNamespaces;
    protected $nodeUtils;

    public function setUp()
    {
        $this->commonNamespaces = new CommonNamespaces();
        $this->nodeUtils = new NodeUtils();

        $this->initFixture();
    }

    protected function initFixture()
    {
        $this->fixture = new InMemoryStore(
            new NodeFactoryImpl($this->nodeUtils),
            new StatementFactoryImpl(),
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl(),
            $this->commonNamespaces
        );

        return $this->fixture;
    }

    /*
     * Tests for addStatements
     */

    // checks, that prefixed and unprefixed URIs stored as unprefixed ones
    public function testAddStatementsIfPrefixedAndUnprefixedURIsAreStoredCorrectly()
    {
        $this->commonNamespaces->add('foo', 'http://foo/');

        $this->initFixture();

        /*
         * check storing prefixed URIs
         */
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'foo:s'),
                new NamedNodeImpl($this->nodeUtils, 'foo:p'),
                new NamedNodeImpl($this->nodeUtils, 'foo:o')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://foo/o'),
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {?s ?p ?o.}')
        );

        /*
         * check storing unprefixed URIs
         */
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://foo/o'),
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {?s ?p ?o.}')
        );
    }

    /*
     * Tests for query
     */

    // check if query method handles prefixed and non-prefixed URIs well
    public function testQueryIfQueryHandlesPrefixedAndNonPrefixedUrisWell()
    {
        $this->commonNamespaces->add('foo', 'http://foo/');

        $this->initFixture();

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'foo:s'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person')
            ),
            // has to be ignored
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o-to-be-ignore')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Label for s'),
            ),
            array(
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://xmlns.com/foaf/0.1/Person'),
            )
        ));
        $expectedResult->setVariables(array('p', 'o'));

        // case 1
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {foo:s ?p ?o.}')
        );

        // case 2
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {<http://foo/s> ?p ?o.}')
        );
    }

    // if we only stored full URI resources, test how the store reacts if we search for a prefixed one
    public function testQuerySearchForPrefixedResource()
    {
        $this->commonNamespaces->add('foo', 'http://foo/');

        $this->initFixture();

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://foo/o'),
            )
        ));
        $expectedResult->setVariables(array('p', 'o'));

        // check for classic SPO
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {foo:s ?p ?o.}')
        );
    }

    // test query if subject is variable, but predicate and object are set
    public function testQuerySetPredicateObjectVariableSubject()
    {
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o'),
                $this->testGraph
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s2'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o'),
                $this->testGraph
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
            ),
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://foo/s2'),
            )
        ));
        $expectedResult->setVariables(array('s'));

        // check for classic SPO
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {?s <http://foo/p> <http://foo/o>. }')
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

    // check ?s ?p ?o.
    //       FILTER (?p = <http://p> || ?p = <http://p1>)
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

    // check _:blank ?p ?o.
    public function testQuerySPOWithFixedBlankNodeSubject()
    {
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s-to-be-ignored'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://xmlns.com/foaf/0.1/Person'),
            )
        ));
        $expectedResult->setVariables(array('p', 'o'));

        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {_:genid1 ?p ?o.}')
        );
    }

    // check <http://> ?p ?o.
    public function testQuerySPOWithFixedSubject()
    {
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s-to-be-ignored'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person')
            ),
            new StatementImpl(
                new BlankNodeImpl('b123'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Label for s'),
            )
        ));
        $expectedResult->setVariables(array('p', 'o'));

        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {<http://s> ?p ?o.}')
        );
    }

    // check ?s ?p ?o.
    //       ?s rdf:type foaf:Person.
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
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Label for s'),
            ),
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://xmlns.com/foaf/0.1/Person'),
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        // check for classic SPO
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {?s ?p ?o. ?s rdf:type foaf:Person.}')
        );
    }
}

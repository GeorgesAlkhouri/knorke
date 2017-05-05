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
        parent::setUp();

        $this->fixture = new InMemoryStore(
            $this->nodeFactory,
            $this->statementFactory,
            $this->queryFactory,
            $this->statementIteratorFactory,
            $this->commonNamespaces,
            $this->rdfHelpers
        );
    }

    /*
     * Tests for addStatements
     */

    // checks, that prefixed and unprefixed URIs stored as unprefixed ones
    public function testAddStatementsIfPrefixedAndUnprefixedURIsAreStoredCorrectly()
    {
        $this->commonNamespaces->add('foo', 'http://foo/');

        /*
         * check storing prefixed URIs
         */
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('foo:s'),
                $this->nodeFactory->createNamedNode('foo:p'),
                $this->nodeFactory->createNamedNode('foo:o')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
                'p' => $this->nodeFactory->createNamedNode('http://foo/p'),
                'o' => $this->nodeFactory->createNamedNode('http://foo/o'),
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
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('http://foo/p'),
                $this->nodeFactory->createNamedNode('http://foo/o')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
                'p' => $this->nodeFactory->createNamedNode('http://foo/p'),
                'o' => $this->nodeFactory->createNamedNode('http://foo/o'),
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

        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('foo:s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            ),
            // has to be ignored
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://foo/p-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://foo/o-to-be-ignore')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label for s'),
            ),
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
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

        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('http://foo/p'),
                $this->nodeFactory->createNamedNode('http://foo/o')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => $this->nodeFactory->createNamedNode('http://foo/p'),
                'o' => $this->nodeFactory->createNamedNode('http://foo/o'),
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
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('http://foo/p'),
                $this->nodeFactory->createNamedNode('http://foo/o'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s2'),
                $this->nodeFactory->createNamedNode('http://foo/p'),
                $this->nodeFactory->createNamedNode('http://foo/o'),
                $this->testGraph
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s2'),
            )
        ));
        $expectedResult->setVariables(array('s'));

        // check for classic SPO
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraph->getUri() .'>
                  WHERE {?s <http://foo/p> <http://foo/o>. }'
            )
        );
    }

    // check super standard queries like ?s ?p ?o, nothing special.
    public function testQuerySPOQuery()
    {
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('http://p'),
                'o' => $this->nodeFactory->createNamedNode('http://o'),
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
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p-not-this'),
                $this->nodeFactory->createNamedNode('http://o1')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('http://p'),
                'o' => $this->nodeFactory->createNamedNode('http://o'),
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
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s-to-be-ignored'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s')
            ),
            $this->statementFactory->createStatement(
                new BlankNodeImpl('genid1'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            )
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
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
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s-to-be-ignored'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            ),
            $this->statementFactory->createStatement(
                new BlankNodeImpl('b123'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label for s'),
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
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            ),
            // has to be ignored
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://p-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://o-to-be-ignore')
            ),
        ));

        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label for s'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
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

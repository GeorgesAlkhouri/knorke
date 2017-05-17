<?php

namespace Tests\Knorke\Store;

use Knorke\DataBlank;
use Knorke\Store\SemanticDbl;
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
use Saft\Sparql\Result\ResultFactoryImpl;
use Saft\Sparql\Result\SetResultImpl;
use Saft\Sparql\SparqlUtils;
use Tests\Knorke\UnitTestCase;

abstract class AbstractStatementStoreTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->initFixture();

        $this->fixture->dropGraph($this->testGraph);
    }

    public function tearDown()
    {
        $this->fixture->dropGraph($this->testGraph);
        $this->fixture->dropGraph(
            $this->nodeFactory->createNamedNode('http://knorke/defaultGraph/')
        );

        parent::tearDown();
    }

    abstract protected function initFixture();

    public function testInit()
    {
        $this->assertTrue(is_object($this->initFixture()));
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
     * Tests for getGraphs
     */

    public function testGetGraphs()
    {
        $this->initFixture();

        // add test graphs
        $this->fixture->createGraph($this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'));
        $this->fixture->createGraph($this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'));

        $graphs = $this->fixture->getGraphs();

        $this->assertTrue(2 <= count($graphs));
        $foundTestGraph1 = false;
        $foundTestGraph2 = false;

        foreach ($graphs as $key => $graph) {
            if ($graph->getUri() == $this->testGraph->getUri() .'1') {
                $foundTestGraph1 = true;
            } elseif ($graph->getUri() == $this->testGraph->getUri() .'2') {
                $foundTestGraph2 = true;
            }
        }

        $this->assertTrue($foundTestGraph1, 'Test graph 1 not found.');
        $this->assertTrue($foundTestGraph2, 'Test graph 2 not found.');
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
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            )
        ));

        // get generated blank node ID
        $nsFoaf = $this->commonNamespaces->getUri('foaf');
        $nsRdf = $this->commonNamespaces->getUri('rdf');
        $result = $this->fixture->query(
            'SELECT * WHERE {
                ?s ?p ?o.
                ?s <'. $nsRdf .'type> <'. $nsFoaf .'Person> .
            }'
        );
        $generatedBlankNodeId = $result[0]['s']->getBlankId();

        // expected result
        $expectedResult = new SetResultImpl(array(
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
            )
        ));

        $expectedResult->setVariables(array('p', 'o'));
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * WHERE {_:'. $generatedBlankNodeId .' ?p ?o.}')
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
                $this->nodeFactory->createBlankNode('b123'),
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

    // check ?s ?p ?o
    //       ?s rdf:type foaf:Person
    //       ?s foo:bar "baz"
    public function testQuerySPOWithTypedSAndUriLiteral2()
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
                $this->nodeFactory->createNamedNode('http://s2'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s2'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('to be ignored')
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
            $this->fixture->query('SELECT * WHERE {
                ?s ?p ?o.
                ?s rdf:type foaf:Person.
                ?s rdfs:label "Label for s".
            }')
        );
    }
}

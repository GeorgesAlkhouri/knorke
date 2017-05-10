<?php

namespace Tests\Knorke;

use Knorke\SemanticDbl;
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

class SemanticDblTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->initFixture();
        $this->fixture->setup();

        $this->fixture->getDb()->q(
            'DELETE FROM graph WHERE uri LIKE ?',
            $this->testGraph->getUri() . '%'
        );

        $this->fixture->dropGraph($this->testGraph);
    }

    public function tearDown()
    {
        $this->fixture->getDb()->q(
            'DELETE FROM graph WHERE uri LIKE ?',
            $this->testGraph->getUri() . '%'
        );

        $this->fixture->dropGraph($this->testGraph);

        parent::tearDown();
    }

    protected function initFixture()
    {
        global $dbConfig;

        $this->fixture = new SemanticDbl(
            $this->nodeFactory,
            $this->statementFactory,
            $this->queryFactory,
            $this->statementIteratorFactory,
            $this->commonNamespaces,
            $this->rdfHelpers
        );

        $this->fixture->connect(
            $dbConfig['user'],
            $dbConfig['pass'],
            $dbConfig['db'],
            $dbConfig['host']
        );
        return $this->fixture;
    }

    public function testInit()
    {
        $this->assertTrue(is_object($this->initFixture()));
    }

    /*
     * Tests for addStatements
     */

    public function testAddStatements()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);

        // remove test content, if available to have a clean test base
        $this->fixture->getDb()->run(
            'DELETE FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .'%'
        );

        // create test data
        $this->fixture->addStatements(
            array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
                    $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
                    $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'3'),
                    $this->testGraph
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
                    $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
                    $this->nodeFactory->createLiteral($this->testGraph->getUri() . ' content'),
                    $this->testGraph
                ),
            )
        );

        // check value entries
        $rows = $this->fixture->getDb()->run(
            'SELECT value, type, language, datatype FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .'%'
        );

        $this->assertEquals(
            array(
                array(
                    'value' => $this->testGraph->getUri() .'1',
                    'type' => 'uri',
                    'language' => null,
                    'datatype' => null
                ),
                array(
                    'value' => $this->testGraph->getUri() .'2',
                    'type' => 'uri',
                    'language' => null,
                    'datatype' => null
                ),
                array(
                    'value' => $this->testGraph->getUri() .'3',
                    'type' => 'uri',
                    'language' => null,
                    'datatype' => null
                ),
                array(
                    'value' => $this->testGraph->getUri() .' content',
                    'type' => 'literal',
                    'language' => null,
                    'datatype' => 'http://www.w3.org/2001/XMLSchema#string'
                ),
            ),
            $rows
        );

        /*
         * check quads
         */
        $valueSubject = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .'1'
        );
        $valuePredicate = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .'2'
        );
        $valueObject = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .'3'
        );
        $valueObject2 = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .' content'
        );

        // check first quad
        $rowFirstQuad = $this->fixture->getDb()->row(
            'SELECT * FROM quad WHERE subject_id = ? AND predicate_id = ? AND object_id = ?',
            $valueSubject, $valuePredicate, $valueObject
        );
        $this->assertTrue(null !== $rowFirstQuad);

        // check second quad
        $rowSecondQuad = $this->fixture->getDb()->row(
            'SELECT * FROM quad WHERE subject_id = ? AND predicate_id = ? AND object_id = ?',
            $valueSubject, $valuePredicate, $valueObject2
        );
        $this->assertTrue(null !== $rowSecondQuad);
    }

    /*
     * Tests for createGraph
     */

    public function testCreateGraph()
    {
        $this->initFixture();
        $db = $this->fixture->getDb();

        // check that test graph is not available already
        $row = $db->row('SELECT uri FROM graph WHERE uri= ?', $this->testGraph->getUri());
        $this->assertNull($row);

        // create
        $this->fixture->createGraph($this->testGraph);

        // check that it is avaiable
        $row = $db->row('SELECT uri FROM graph WHERE uri= ?', $this->testGraph->getUri());
        $this->assertTrue(null !== $row);
    }

    /*
     * Tests for deleteMatchingStatements
     */

    public function testDeleteMatchingStatements()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);

        // remove test content, if available to have a clean test base
        $this->fixture->getDb()->run(
            'DELETE FROM value WHERE value LIKE ?',
            $this->testGraph->getUri() .'%'
        );

        /*
         * test data
         */
        $statement1 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'3'),
            $this->testGraph
        );

        $statement2 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'4'),
            $this->testGraph
        );

        $statement3 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createLiteral($this->testGraph->getUri() . ' content'),
            $this->testGraph
        );

        $statement4 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'3'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'5'),
            $this->testGraph
        );

        // create test data
        $this->fixture->addStatements(array($statement1, $statement2, $statement3, $statement4));

        $quads = $this->fixture->getDb()->run('SELECT * FROM quad WHERE graph = ?', $this->testGraph->getUri());
        $this->assertEquals(4, count($quads));

        /*
         * remove by set s, p, o
         */
        $this->fixture->deleteMatchingStatements($statement3);

        $quads = $this->fixture->getDb()->run('SELECT * FROM quad WHERE graph = ?', $this->testGraph->getUri());
        $this->assertEquals(3, count($quads));

        /*
         * remove by set s, p, o
         */
        $this->fixture->deleteMatchingStatements($this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createAnyPattern(),
            $this->testGraph
        ));

        $quads = $this->fixture->getDb()->run('SELECT * FROM quad WHERE graph = ?', $this->testGraph->getUri());
        $this->assertEquals(1, count($quads));

        // $statement4 should remains
    }

    /*
     * Tests for dropGraph
     */

    public function testDropGraph()
    {
        $this->initFixture();
        $db = $this->fixture->getDb();

        // check that test graph is available
        $this->fixture->createGraph($this->testGraph);

        // add test data
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createNamedNode('http://foo1'),
                $this->nodeFactory->createNamedNode('http://foo2'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo2'),
                $this->nodeFactory->createNamedNode('http://foo3'),
                $this->nodeFactory->createNamedNode('http://foo4'),
                $this->testGraph
            )
        ));
        $rows = $db->run('SELECT graph FROM quad WHERE graph = ?', $this->testGraph->getUri());
        $this->assertEquals(2, count($rows));

        $row = $db->row('SELECT uri FROM graph WHERE uri = ?', $this->testGraph->getUri());
        $this->assertTrue(null !== $row);

        // drop it
        $this->fixture->dropGraph($this->testGraph);

        // check that it was removed
        $row = $db->row('SELECT uri FROM graph WHERE uri = ?', $this->testGraph->getUri());
        $this->assertNull($row);

        // check that data of the graph was removed too
        $rows = $db->run('SELECT graph FROM quad WHERE graph = ?', $this->testGraph->getUri());
        $this->assertEquals(0, count($rows));
    }

    /*
     * Tests for getGraphs
     */

    public function testGetGraphs()
    {
        $this->initFixture();
        $db = $this->fixture->getDb();

        // add test graphs
        $this->fixture->createGraph($this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'));
        $this->fixture->createGraph($this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'));

        $graphs = $this->fixture->getGraphs();

        $this->assertTrue(2 <= count($graphs));

        foreach ($graphs as $key => $graph) {
            if (0 == $key) {
                $this->assertEquals($this->testGraph->getUri() .'1', $graph->getUri());
            } elseif (1 == $key) {
                $this->assertEquals($this->testGraph->getUri() .'2', $graph->getUri());
            }
        }
    }

    /*
     * Tests for getMatchingStatements
     */

    // not implemented, therefore check for thrown exception
    public function testGetMatchingStatements()
    {
        $this->initFixture();

        $this->expectException('\Exception');

        $this->fixture->getMatchingStatements(
            $this->statementFactory->createStatement($this->testGraph, $this->testGraph, $this->testGraph)
        );
    }

    /*
     * Tests for getStatementsFromGraph
     */

    public function testGetStatementsFromGraph()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);
        /*
        $statement1 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'3'),
            $this->testGraph
        );

        $statement2 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createLiteral($this->testGraph->getUri() . ' content'),
            $this->testGraph
        );*/

        $statement3 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createBlankNode('foobar'),
            $this->testGraph
        );

        // add test statements to store
        $this->fixture->addStatements(array(/*$statement1, $statement2, */$statement3));

        // build expected result
        $expectedResult = $this->statementIteratorFactory->createStatementIteratorFromArray(array(
            /*$statement1,
            $statement2,*/
            $statement3,
        ));

        $this->assertStatementIteratorEquals(
            $expectedResult,
            $this->fixture->getStatementsFromGraph($this->testGraph),
            true
        );
    }

    /*
     * Tests for hasMatchingStatement
     */

    // not implemented, therefore check for thrown exception
    public function testHasMatchingStatements()
    {
        $this->initFixture();

        $this->expectException('\Exception');

        $this->fixture->hasMatchingStatement(
            $this->statementFactory->createStatement($this->testGraph, $this->testGraph, $this->testGraph)
        );
    }

    /*
     * Tests for isSetup
     */

    public function testIsSetup()
    {
        $this->fixture->getDb()->run('DROP TABLE IF EXISTS graph, quad, value');

        $this->assertFalse($this->fixture->isSetup());

        $this->fixture->setup();

        $this->assertTrue($this->fixture->isSetup());
    }

    /*
     * Tests for deleteMatchingStatements
     */

    public function testQuerySPO()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);

        // remove test content, if available to have a clean test base
        $this->fixture->getDb()->run('DELETE FROM value WHERE value LIKE ?', $this->testGraph->getUri() .'%');

        /*
         * create test data
         */
        $statement1 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'3'),
            $this->testGraph
        );

        $statement2 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
            $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
            $this->nodeFactory->createLiteral($this->testGraph->getUri() . ' content'),
            $this->testGraph
        );

        $this->fixture->addStatements(array($statement1, $statement2));

        // prepare data to check against
        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
                'p' => $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
                'o' => $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'3'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'1'),
                'p' => $this->nodeFactory->createNamedNode($this->testGraph->getUri() .'2'),
                'o' => $this->nodeFactory->createLiteral($this->testGraph->getUri() .' content')
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        // run query and check result
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {?s ?p ?o.}')
        );
    }

    // check if query method handles prefixed and non-prefixed URIs well
    public function testQueryIfQueryHandlesPrefixedAndNonPrefixedUrisWell()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->commonNamespaces->add('foo', 'http://foo/');
        $this->initFixture();
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('foo:s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person'),
                $this->testGraph
            ),
            // has to be ignored
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://foo/p-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://foo/o-to-be-ignore'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {foo:s ?p ?o.}')
        );
        // case 2
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {<http://foo/s> ?p ?o.}')
        );
    }

    // check gathering from multiple graphs
    public function testQueryGatheringFromMultipleGraphs()
    {
        $this->fixture->createGraph($this->testGraph);

        // create second graph
        $testGraph2 = $this->nodeFactory->createNamedNode($this->testGraph->getUri() . '2');
        $this->fixture->createGraph($testGraph2);

        $this->initFixture();

        /*
         * data for graph 1
         */
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label of s from first graph'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person-from-first-graph'),
                $this->testGraph
            ),
        ));

        /*
         * data for graph 2
         */
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label of s from second graph'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person-from-second-graph'),
                $this->testGraph
            ),
        ));

        /*
         * ?s ?p ?o
         */
        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label of s from first graph'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person-from-first-graph'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label of s from second graph'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person-from-second-graph'),
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        // compare actual and expected result
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraph->getUri() .'>
                   FROM <'. $testGraph2->getUri() .'>
                  WHERE {?s ?p ?o.}'
            )
        );

        /*
         * <http://...> ?p ?o
         */
        $expectedResult = new SetResultImpl(array(
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label of s from first graph'),
            ),
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person-from-first-graph'),
            ),
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Label of s from second graph'),
            ),
            array(
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person-from-second-graph'),
            )
        ));
        $expectedResult->setVariables(array('p', 'o'));

        // compare actual and expected result
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraph->getUri() .'>
                   FROM <'. $testGraph2->getUri() .'>
                  WHERE {<http://foo/s> ?p ?o.}'
            )
        );

        /*
         * ?s <http://...type> <http://...#Person>
         */
        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://foo/s'),
            )
        ));
        $expectedResult->setVariables(array('s'));

        // compare actual and expected result
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraph->getUri() .'>
                   FROM <'. $testGraph2->getUri() .'>
                  WHERE {
                      ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>
                            <http://xmlns.com/foaf/0.1/Person-from-second-graph> .
                  }'
            )
        );
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraph->getUri() .'>
                   FROM <'. $testGraph2->getUri() .'>
                  WHERE {
                      ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>
                            <http://xmlns.com/foaf/0.1/Person-from-first-graph> .
                  }'
            )
        );
    }

    // if we only stored full URI resources, test how the store reacts if we search for a prefixed one
    public function testQuerySearchForPrefixedResource()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->commonNamespaces->add('foo', 'http://foo/');
        $this->initFixture();
        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo/s'),
                $this->nodeFactory->createNamedNode('http://foo/p'),
                $this->nodeFactory->createNamedNode('http://foo/o'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {foo:s ?p ?o.}')
        );
    }

    // test query if subject is variable, but predicate and object are set
    public function testQuerySetPredicateObjectVariableSubject()
    {
        $this->fixture->createGraph($this->testGraph);

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
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {?s ?p ?o.}')
        );
    }

    // check ?s ?p ?o.
    //       FILTER (?p = <http://p> || ?p = <http://p1>)
    public function testQuerySPOQueryWithFilter()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p-not-this'),
                $this->nodeFactory->createNamedNode('http://o1'),
                $this->testGraph
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
                   FROM <'. $this->testGraph->getUri() .'>
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
                   FROM <'. $this->testGraph->getUri() .'>
                  WHERE {
                    ?s ?p ?o.
                    FILTER (?p = <http://p>)
                }'
            )
        );
    }

    // check <http://> ?p ?o.
    public function testQuerySPOWithFixedSubject()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s-to-be-ignored'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('b123'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {<http://s> ?p ?o.}')
        );
    }

    // query a test base with many triples
    public function testQuerySPOWithManyTriples()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);

        for ($i=0; $i < 500; $i++) {
            $this->fixture->addStatements(array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://s/' . $i),
                    $this->nodeFactory->createNamedNode('rdfs:label'),
                    $this->nodeFactory->createLiteral('Label for s'),
                    $this->testGraph
                ),
            ));
        }

        $result = $this->fixture->query('SELECT * FROM <'. $this->testGraph->getUri() .'> WHERE {?s ?p ?o.}');

        $this->assertEquals(500, count($result));
    }

    // check ?s ?p ?o.
    //       ?s rdf:type foaf:Person.
    public function testQuerySPOWithTypedSQuery()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Label for s'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('rdf:type'),
                $this->nodeFactory->createNamedNode('foaf:Person'),
                $this->testGraph
            ),
            // has to be ignored
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://p-to-be-ignore'),
                $this->nodeFactory->createNamedNode('http://o-to-be-ignore'),
                $this->testGraph
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
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraph->getUri() .'>
                  WHERE {?s ?p ?o. ?s rdf:type foaf:Person.}'
            )
        );
    }

    /*
     * Test setup
     */
    public function testSetup()
    {
        $this->fixture->getDb()->run('DROP TABLE IF EXISTS graph, quad, value');

        $this->fixture->setup();

        $tables = $this->fixture->getDb()->run('SHOW TABLES');
        $this->assertEquals(3, count($tables));
    }
}

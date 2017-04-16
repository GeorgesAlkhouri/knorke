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
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\ResultFactoryImpl;
use Saft\Sparql\Result\SetResultImpl;
use Saft\Sparql\SparqlUtils;

class SemanticDblTest extends UnitTestCase
{
    protected $commonNamespaces;

    public function setUp()
    {
        parent::setUp();

        $this->commonNamespaces = new CommonNamespaces();
        $this->statementFactory = new StatementFactoryImpl();

        $this->initFixture();
        $this->fixture->setup();

        $this->fixture->getDb()->q(
            'DELETE FROM graph WHERE uri LIKE ?',
            $this->testGraphUri . '%'
        );

        $this->fixture->dropGraph($this->testGraph);
    }

    public function tearDown()
    {
        $this->fixture->getDb()->q(
            'DELETE FROM graph WHERE uri LIKE ?',
            $this->testGraphUri . '%'
        );

        $this->fixture->dropGraph($this->testGraph);

        parent::tearDown();
    }

    protected function initFixture()
    {
        $this->fixture = new SemanticDbl(
            $this->nodeFactory,
            $this->statementFactory,
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl(),
            $this->commonNamespaces
        );

        $this->fixture->connect('root', 'Pass123', 'knorke', 'db');
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
            $this->testGraphUri .'%'
        );

        // create test data
        $this->fixture->addStatements(
            array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
                    $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
                    $this->nodeFactory->createNamedNode($this->testGraphUri .'3'),
                    $this->testGraph
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
                    $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
                    $this->nodeFactory->createLiteral($this->testGraphUri . ' content'),
                    $this->testGraph
                ),
            )
        );

        // check value entries
        $rows = $this->fixture->getDb()->run(
            'SELECT value, type, language, datatype FROM value WHERE value LIKE ?',
            $this->testGraphUri .'%'
        );

        $this->assertEquals(
            array(
                array(
                    'value' => $this->testGraphUri .'1',
                    'type' => 'uri',
                    'language' => null,
                    'datatype' => null
                ),
                array(
                    'value' => $this->testGraphUri .'2',
                    'type' => 'uri',
                    'language' => null,
                    'datatype' => null
                ),
                array(
                    'value' => $this->testGraphUri .'3',
                    'type' => 'uri',
                    'language' => null,
                    'datatype' => null
                ),
                array(
                    'value' => $this->testGraphUri .' content',
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
            $this->testGraphUri .'1'
        );
        $valuePredicate = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraphUri .'2'
        );
        $valueObject = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraphUri .'3'
        );
        $valueObject2 = $this->fixture->getDb()->cell(
            'SELECT id FROM value WHERE value LIKE ?',
            $this->testGraphUri .' content'
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
        $row = $db->row('SELECT uri FROM graph WHERE uri= ?', $this->testGraphUri);
        $this->assertNull($row);

        // create
        $this->fixture->createGraph($this->testGraph);

        // check that it is avaiable
        $row = $db->row('SELECT uri FROM graph WHERE uri= ?', $this->testGraphUri);
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
            $this->testGraphUri .'%'
        );

        /*
         * test data
         */
        $statement1 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'3'),
            $this->testGraph
        );

        $statement2 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'4'),
            $this->testGraph
        );

        $statement3 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createLiteral($this->testGraphUri . ' content'),
            $this->testGraph
        );

        $statement4 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'3'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'5'),
            $this->testGraph
        );

        // create test data
        $this->fixture->addStatements(array($statement1, $statement2, $statement3, $statement4));

        $quads = $this->fixture->getDb()->run('SELECT * FROM quad WHERE graph = ?', $this->testGraphUri);
        $this->assertEquals(4, count($quads));

        /*
         * remove by set s, p, o
         */
        $this->fixture->deleteMatchingStatements($statement3);

        $quads = $this->fixture->getDb()->run('SELECT * FROM quad WHERE graph = ?', $this->testGraphUri);
        $this->assertEquals(3, count($quads));

        /*
         * remove by set s, p, o
         */
        $this->fixture->deleteMatchingStatements($this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createAnyPattern(),
            $this->testGraph
        ));

        $quads = $this->fixture->getDb()->run('SELECT * FROM quad WHERE graph = ?', $this->testGraphUri);
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
        $row = $db->row('SELECT uri FROM graph WHERE uri = ?', $this->testGraphUri);
        $this->assertTrue(null !== $row);

        // drop it
        $this->fixture->dropGraph($this->testGraph);

        // check that it was removed
        $row = $db->row('SELECT uri FROM graph WHERE uri = ?', $this->testGraphUri);
        $this->assertNull($row);
    }

    /*
     * Tests for getGraphs
     */

    public function testGetGraphs()
    {
        $this->initFixture();
        $db = $this->fixture->getDb();

        // add test graphs
        $this->fixture->createGraph($this->nodeFactory->createNamedNode($this->testGraphUri .'1'));
        $this->fixture->createGraph($this->nodeFactory->createNamedNode($this->testGraphUri .'2'));

        $graphs = $this->fixture->getGraphs();

        $this->assertTrue(2 <= count($graphs));

        foreach ($graphs as $key => $graph) {
            if (0 == $key) {
                $this->assertEquals($this->testGraphUri .'1', $graph->getUri());
            } elseif (1 == $key) {
                $this->assertEquals($this->testGraphUri .'2', $graph->getUri());
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
        $this->fixture->getDb()->run('DELETE FROM value WHERE value LIKE ?', $this->testGraphUri .'%');

        /*
         * create test data
         */
        $statement1 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'3'),
            $this->testGraph
        );

        $statement2 = $this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
            $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
            $this->nodeFactory->createLiteral($this->testGraphUri . ' content'),
            $this->testGraph
        );

        $this->fixture->addStatements(array($statement1, $statement2));

        // prepare data to check against
        $expectedResult = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
                'p' => $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
                'o' => $this->nodeFactory->createNamedNode($this->testGraphUri .'3'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode($this->testGraphUri .'1'),
                'p' => $this->nodeFactory->createNamedNode($this->testGraphUri .'2'),
                'o' => $this->nodeFactory->createLiteral($this->testGraphUri .' content')
            )
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        // run query and check result
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {?s ?p ?o.}')
        );
    }

    // check if query method handles prefixed and non-prefixed URIs well
    public function testQueryIfQueryHandlesPrefixedAndNonPrefixedUrisWell()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->commonNamespaces->add('foo', 'http://foo/');
        $this->initFixture();
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'foo:s'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s'),
                $this->testGraph
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person'),
                $this->testGraph
            ),
            // has to be ignored
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o-to-be-ignore'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {foo:s ?p ?o.}')
        );
        // case 2
        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {<http://foo/s> ?p ?o.}')
        );
    }

    // if we only stored full URI resources, test how the store reacts if we search for a prefixed one
    public function testQuerySearchForPrefixedResource()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->commonNamespaces->add('foo', 'http://foo/');
        $this->initFixture();
        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foo/s'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/p'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo/o'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {foo:s ?p ?o.}')
        );
    }

    // check super standard queries like ?s ?p ?o, nothing special.
    public function testQuerySPOQuery()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'http://p'),
                new NamedNodeImpl($this->nodeUtils, 'http://o'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {?s ?p ?o.}')
        );
    }

    // check ?s ?p ?o.
    //       FILTER (?p = <http://p> || ?p = <http://p1>)
    public function testQuerySPOQueryWithFilter()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'http://p'),
                new NamedNodeImpl($this->nodeUtils, 'http://o'),
                $this->testGraph
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'http://p-not-this'),
                new NamedNodeImpl($this->nodeUtils, 'http://o1'),
                $this->testGraph
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
                   FROM <'. $this->testGraphUri .'>
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
                   FROM <'. $this->testGraphUri .'>
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
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s-to-be-ignored'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s'),
                $this->testGraph
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {_:genid1 ?p ?o.}')
        );
    }

    // check <http://> ?p ?o.
    public function testQuerySPOWithFixedSubject()
    {
        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s'),
                $this->testGraph
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s-to-be-ignored'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person'),
                $this->testGraph
            ),
            new StatementImpl(
                new BlankNodeImpl('b123'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person'),
                $this->testGraph
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
            $this->fixture->query('SELECT * FROM <'. $this->testGraphUri .'> WHERE {<http://s> ?p ?o.}')
        );
    }

    // check ?s ?p ?o.
    //       ?s rdf:type foaf:Person.
    public function testQuerySPOWithTypedSQuery()
    {
        $this->initFixture();

        $this->fixture->createGraph($this->testGraph);

        $this->fixture->addStatements(array(
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'Label for s'),
                $this->testGraph
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s'),
                new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                new NamedNodeImpl($this->nodeUtils, 'foaf:Person'),
                $this->testGraph
            ),
            // has to be ignored
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://s-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://p-to-be-ignore'),
                new NamedNodeImpl($this->nodeUtils, 'http://o-to-be-ignore'),
                $this->testGraph
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
            $this->fixture->query(
                'SELECT *
                   FROM <'. $this->testGraphUri .'>
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

<?php

namespace Tests\Knorke;

use Knorke\DataBlank;
use Knorke\DataBlankHelper;
use Knorke\Exception\KnorkeException;
use Knorke\InMemoryStore;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\NodeUtils;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class DataBlankHelperTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new DataBlankHelper(
            $this->commonNamespaces,
            $this->statementFactory,
            $this->nodeFactory,
            $this->rdfHelpers,
            $this->store,
            $this->testGraph
        );

        $this->store->dropGraph($this->testGraph);
        $this->store->createGraph($this->testGraph);
    }

    /*
     * Tests for createDataBlank
     */

    public function testCreateDataBlank()
    {
        $this->assertTrue($this->fixture->createDataBlank() instanceof DataBlank);
    }

    /*
     * Tests for dispense
     */

    public function testDispense()
    {
        $personHash = 'foobar';
        $dataBlank = $this->fixture->dispense('foaf:Person', $personHash);

        $this->assertEquals('foaf:Person/foaf-person/id/foobar', $dataBlank['_idUri']);
        $this->assertEquals('foaf:Person', $dataBlank['rdf:type']);
    }

    // with base URI provided, the resource URI changes
    public function testDispenseWithBaseUri()
    {
        $personHash = 'foobar';
        $dataBlank = $this->fixture->dispense('foaf:Person', $personHash, 'http://foobar/');

        $this->assertEquals('http://foobar/foaf-person/id/'. $personHash, $dataBlank['_idUri']);
        $this->assertEquals('foaf:Person', $dataBlank['rdf:type']);
    }

    /*
     * Tests for find
     */

    public function testFind()
    {
        $resourceUri = 'http://foobar/foaf-person/id/foobar';

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createNamedNode('http://bar'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createBlankNode('bn1'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('bn1'),
                $this->nodeFactory->createNamedNode('http://foobar'),
                $this->nodeFactory->createNamedNode('http://baz')
            )
        ));

        // get blank node ID
        $result = $this->store->query('SELECT * WHERE { ?s <http://foobar> <http://baz> . }');
        $blankNodeId = array_values($result->getArrayCopy())[0]['s']->toNQuads();

        // compare
        $this->assertEquals(
            array(
                $resourceUri => array(
                    '_idUri' => $resourceUri,
                    'rdf:type' => 'foaf:Person',
                    'http://foo' => array(
                        'http://bar',
                        array(
                            '_idUri' => $blankNodeId,
                            'http://foobar' => 'http://baz'
                        )
                    )
                )
            ),
            array(
                $resourceUri => array_values($this->fixture->find('foaf:Person'))[0]->getArrayCopy()
            )
        );
    }

    public function testFindComplex()
    {
        $resourceUri = 'http://foobar/foaf-person/id/foobar';

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createNamedNode('http://bar'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createBlankNode('bn1'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('bn1'),
                $this->nodeFactory->createNamedNode('http://foobar'),
                $this->nodeFactory->createNamedNode('http://baz')
            ),
            /*
             * another resource
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri.'/another-one'),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri.'/another-one'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('Another Person'),
                $this->testGraph
            )
        ));

        // get blank node ID
        $result = $this->store->query('SELECT * WHERE { ?s <http://foobar> <http://baz> . }');
        $blankNodeId = array_values($result->getArrayCopy())[0]['s']->toNQuads();

        $persons = array_values($this->fixture->find('foaf:Person'));

        // compare
        $this->assertEquals(
            array(
                $resourceUri => array(
                    '_idUri' => $resourceUri,
                    'rdf:type' => 'foaf:Person',
                    'http://foo' => array(
                        'http://bar',
                        array(
                            '_idUri' => $blankNodeId,
                            'http://foobar' => 'http://baz'
                        )
                    )
                ),
                $resourceUri . '/another-one' => array(
                    '_idUri' => $resourceUri . '/another-one',
                    'rdf:type' => 'foaf:Person',
                    'rdfs:label' => 'Another Person'
                )
            ),
            array(
                $resourceUri                    => $persons[0]->getArrayCopy(),
                $resourceUri . '/another-one'   => $persons[1]->getArrayCopy()
            )
        );
    }

    public function testFindWherePart()
    {
        $resourceUri = 'http://foobar/foaf-person/id/foobar';

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            // important triple, used for later search
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('foobar'),
                $this->testGraph
            ),
            // following statements have to be ignored
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://to-be-ignored'),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://to-be-ignored'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('to be ignored'),
                $this->testGraph
            ),
        ));

        // compare
        $this->assertEquals(
            array(
                $resourceUri => array(
                    '_idUri' => $resourceUri,
                    'rdf:type' => 'foaf:Person',
                    'rdfs:label' => 'foobar'
                )
            ),
            array(
                $resourceUri => array_values(
                    $this->fixture->find('foaf:Person', '?s rdfs:label "foobar"')
                )[0]->getArrayCopy()
            )
        );
    }

    /*
     * Tests for findOne
     */

    public function testFindOne()
    {
        $resourceUri = 'http://foobar/foaf-person/id/foobar';

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createLiteral('bar'),
                $this->testGraph
            ),
            // second resource
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri .'/second'),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri .'/second'),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createLiteral('too be ignored'),
                $this->testGraph
            ),
        ));

        // compare
        $this->assertEquals(
            array(
                '_idUri' => $resourceUri,
                'rdf:type' => 'foaf:Person',
                'http://foo' => 'bar'
            ),
            $this->fixture->findOne('foaf:Person', '?s <http://foo> "bar".')->getArrayCopy()
        );
    }

    // test for exception if more than one entry was found
    public function testFindOneMultipleFindings()
    {
        $resourceUri = 'http://foobar/foaf-person/id/foobar';

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri .'/second'),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            )
        ));

        $this->expectException('Knorke\Exception\KnorkeException');

        $this->fixture->findOne('foaf:Person');
    }

    /*
     * Tests for load
     */

    public function testLoad()
    {
        $resourceUri = 'http://foobar/foaf-person/id/foobar';

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode($resourceUri),
                $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
                $this->testGraph
            )
        ));

        // load entry with URI: foaf:Person/id/foobar
        $dataBlank = $this->fixture->load($resourceUri);

        $this->assertEquals('http://foobar/foaf-person/id/foobar', $dataBlank['_idUri']);
        $this->assertEquals('foaf:Person', $dataBlank['rdf:type']);
    }

    /*
     * Tests for store
     */

    public function testStore()
    {
        $dataBlank = $this->fixture->dispense('foaf:Person', 'foobar', 'http://foobar/');


        $dataBlank['rdfs:label'] = 'Geiles Label';

        $this->fixture->store($dataBlank);

        /*
         * check generated store content
         */
        $expected = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode($dataBlank['_idUri']),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person')
            ),
            array(
                's' => $this->nodeFactory->createNamedNode($dataBlank['_idUri']),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#label'),
                'o' => $this->nodeFactory->createLiteral('Geiles Label')
            )
        ));
        $expected->setVariables(array('s', 'p', 'o'));

        $this->assertSetIteratorEquals(
            $expected,
            $this->store->query('SELECT * FROM <'. $this->testGraph .'> WHERE {?s ?p ?o.}')
        );

        /*
         * load blank from store and check it
         */
        $blankCopy = $this->fixture->load($dataBlank['_idUri']);

        $blankToCheckAgainst = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        $blankToCheckAgainst['_idUri'] = 'http://foobar/foaf-person/id/foobar';
        $blankToCheckAgainst['rdf:type'] = 'foaf:Person';
        $blankToCheckAgainst['rdfs:label'] = 'Geiles Label';

        $this->assertEquals($blankToCheckAgainst, $blankCopy);
    }

    /*
     * Tests for trash
     */

    public function testTrash()
    {
        $this->commonNamespaces->add('test', 'http://test/');

        $blank = $this->fixture->dispense('test:User');
        $blank['rdfs:label'] = 'label';

        // store blank
        $this->fixture->store($blank);

        // check content
        $result = $this->store->query('SELECT * FROM <'. $this->testGraph .'> WHERE {?s ?p ?o.}');
        $this->assertEquals(2, count($result->getArrayCopy()));

        $this->fixture->trash($blank);

        // check content again
        $result = $this->store->query('SELECT * FROM <'. $this->testGraph .'> WHERE {?s ?p ?o.}');
        $this->assertEquals(0, count($result->getArrayCopy()));
    }
}

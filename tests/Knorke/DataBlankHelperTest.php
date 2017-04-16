<?php

namespace Tests\Knorke;

use Knorke\DataBlank;
use Knorke\DataBlankHelper;
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
    protected $commonNamespaces;
    protected $store;

    public function setUp()
    {
        parent::setUp();

        $this->commonNamespaces = new CommonNamespaces();

        $this->store = new InMemoryStore(
            $this->nodeFactory,
            $this->statementFactory,
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl(),
            new CommonNamespaces()
        );
        $this->fixture = new DataBlankHelper(
            new CommonNamespaces(),
            $this->statementFactory,
            $this->nodeFactory,
            $this->nodeUtils,
            $this->store,
            $this->testGraph
        );

        $this->store->dropGraph($this->testGraph);
        $this->store->createGraph($this->testGraph);
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
        $result = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode($dataBlank['_idUri']),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person')
            ),
            array(
                's' => $this->nodeFactory->createNamedNode($dataBlank['_idUri']),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/2000/01/rdf-schema#'),
                'o' => $this->nodeFactory->createLiteral('Geiles Label')
            )
        ));
        $result->setVariables(array('s', 'p', 'o'));

        $this->assertSetIteratorEquals(
            $result,
            $this->store->query('SELECT * FROM <'. $this->testGraph .'> WHERE {?s ?p ?o.}')
        );

        /*
         * load blank from store and check it
         */
        $blankCopy = $this->fixture->load($dataBlank['_idUri']);

        $blankToCheckAgainst = new DataBlank($this->commonNamespaces);
        $blankToCheckAgainst['_idUri'] = 'http://foobar/foaf-person/id/foobar';
        $blankToCheckAgainst['rdf:type'] = 'foaf:Person';
        $blankToCheckAgainst['rdfs:label'] = 'Geiles Label';

        $this->assertEquals($blankToCheckAgainst, $blankCopy);
    }
}

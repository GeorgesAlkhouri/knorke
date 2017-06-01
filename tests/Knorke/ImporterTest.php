<?php

namespace Tests\Knorke;

use Knorke\Importer;
use Saft\Sparql\Result\SetResultImpl;

class ImporterTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new Importer(
            $this->store,
            $this->parserFactory,
            $this->nodeFactory,
            $this->statementFactory,
            $this->rdfHelpers,
            $this->commonNamespaces
        );
    }

    /*
     * Tests for getSerialization
     */

    public function testGetSerialization()
    {
        $this->assertEquals('turtle', $this->fixture->getSerialization('@prefix foo:'));

        $this->assertEquals(null, $this->fixture->getSerialization(''));
    }

    public function testGetSerializationIsNotAString()
    {
        $this->expectException('\Knorke\Exception\KnorkeException');

        $this->fixture->getSerialization(0);
    }

    /*
     * Tests for importDataValidationArray
     */

    public function testImportDataValidationArray()
    {
        $this->commonNamespaces->add('foo', 'http://foo/');

        $selectAll = 'SELECT * FROM <'. $this->testGraph .'> WHERE {?s ?p ?o.}';

        $startResource = $this->nodeFactory->createNamedNode($this->testGraph .'res1');

        $this->assertCountStatementIterator(0, $this->store->query($selectAll));

        $this->fixture->importDataValidationArray(
            array(
                'rdf:type' => 'foo:User',
                'foo:has-rights' => array(
                    array(
                        'rdfs:label' => 'foo',
                        'foo:knows' => array(
                            'http://rdf/type' => 'http://foaf/Person'
                        )
                    ),
                    array(
                        'rdfs:label' => 'bar',
                        'foo:a-number' => 43
                    )
                )
            ),
            $startResource,
            $this->testGraph
        );

        $this->assertCountStatementIterator(8, $this->store->query($selectAll));

        // get referenced blank node
        $result = $this->store->query('SELECT * FROM <'. $this->testGraph .'> WHERE {<'.$startResource.'> ?p ?o.}');
        $blankNode1 = array_values($result->getArrayCopy())[1]['o'];
        $blankNode2 = array_values($result->getArrayCopy())[2]['o'];

        $result = $this->store->query('SELECT * FROM <'. $this->testGraph .'> WHERE {<'.$blankNode1.'> ?p ?o.}');
        $blankNode3 = $result->getArrayCopy()[1]['o'];

        // expect
        $expectedResult = new SetResultImpl(array(
            array(
                's' => $startResource,
                'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('rdf') .'type'),
                'o' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('foo') .'User')
            ),
                // has-rights entry 1
                array(
                    's' => $startResource,
                    'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('foo') .'has-rights'),
                    'o' => $blankNode1
                ),
                array(
                    's' => $blankNode1,
                    'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('rdfs') .'label'),
                    'o' => $this->nodeFactory->createLiteral('foo')
                ),
                array(
                    's' => $blankNode1,
                    'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('foo') .'knows'),
                    'o' => $blankNode3
                ),
                    // sub reference
                    array(
                        's' => $blankNode3,
                        'p' => $this->nodeFactory->createNamedNode('http://rdf/type'),
                        'o' => $this->nodeFactory->createNamedNode('http://foaf/Person')
                    ),
                // has-rights entry 2
                array(
                    's' => $startResource,
                    'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('foo') .'has-rights'),
                    'o' => $blankNode2
                ),
                array(
                    's' => $blankNode2,
                    'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('rdfs') .'label'),
                    'o' => $this->nodeFactory->createLiteral('bar')
                ),
                array(
                    's' => $blankNode2,
                    'p' => $this->nodeFactory->createNamedNode($this->commonNamespaces->getUri('foo') .'a-number'),
                    'o' => $this->nodeFactory->createLiteral('43')
                ),
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->store->query($selectAll)
        );
    }

    public function testImportDataValidationArrayEmptyArrayAsParameter()
    {
        $this->expectException('\Knorke\Exception\KnorkeException');

        $this->fixture->importDataValidationArray(array(), $this->testGraph, $this->testGraph);
    }

    /*
     * Tests for transformPhpArrayToStatementArray
     */

    public function testTransformPhpArrayToStatementArray()
    {
        $result = $this->fixture->transformPhpArrayToStatementArray(
            $this->testGraph,
            array(
                'http://foo/' => 'http://foo/Person',
                'http://foo/1' => array(
                    array(
                        'http://bar/1' => 'http://baz/1',
                        'http://bar/2' => 'http://baz/1',
                        'http://bar/3' => 'literal1'
                    ),
                    array(
                        'http://bar/4' => 'http://baz/2',
                        'http://bar/5' => 'http://baz/2',
                        'http://bar/6' => 'literal2'
                    )
                ),
                'http://foo/2' => array(
                    'http://bar/7' => 'http://baz/3',
                    'http://bar/8' => 'http://baz/3',
                    'http://bar/9' => 'literal3'
                ),
            )
        );

        $this->assertEquals(
            array(
                // block 1
                $this->statementFactory->createStatement(
                    $result[0]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/7'),
                    $this->nodeFactory->createNamedNode('http://baz/3')
                ),
                $this->statementFactory->createStatement(
                    $result[0]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/8'),
                    $this->nodeFactory->createNamedNode('http://baz/3')
                ),
                $this->statementFactory->createStatement(
                    $result[0]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/9'),
                    $this->nodeFactory->createLiteral('literal3')
                ),
                // block 2
                $this->statementFactory->createStatement(
                    $result[4]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/4'),
                    $this->nodeFactory->createNamedNode('http://baz/2')
                ),
                $this->statementFactory->createStatement(
                    $result[4]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/5'),
                    $this->nodeFactory->createNamedNode('http://baz/2')
                ),
                $this->statementFactory->createStatement(
                    $result[4]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/6'),
                    $this->nodeFactory->createLiteral('literal2')
                ),
                // block 3
                $this->statementFactory->createStatement(
                    $result[6]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/1'),
                    $this->nodeFactory->createNamedNode('http://baz/1')
                ),
                $this->statementFactory->createStatement(
                    $result[6]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/2'),
                    $this->nodeFactory->createNamedNode('http://baz/1')
                ),
                $this->statementFactory->createStatement(
                    $result[6]->getSubject(),
                    $this->nodeFactory->createNamedNode('http://bar/3'),
                    $this->nodeFactory->createLiteral('literal1')
                ),
                // block 4
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://foo/'),
                    $this->nodeFactory->createNamedNode('http://foo/Person')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://foo/1'),
                    $result[6]->getSubject()
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://foo/1'),
                    $result[4]->getSubject()
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://foo/2'),
                    $result[0]->getSubject()
                ),
            ),
            $result
        );
    }
}

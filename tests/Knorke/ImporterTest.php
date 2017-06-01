<?php

namespace Tests\Knorke;

use Knorke\Importer;

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

        $this->assertCountStatementIterator(0, $this->store->query($selectAll));

        $startResource = $this->nodeFactory->createNamedNode($this->testGraph .'res1');

        $this->fixture->importDataValidationArray(
            array(
                'rdf:type' => 'foo:User',
                'foo:has-rights' => array(
                    'rdfs:label' => 'foo',
                    'foo:a-number' => 42
                )
            ),
            $startResource,
            $this->testGraph
        );

        $this->assertCountStatementIterator(4, $this->store->query($selectAll));
    }

    public function testImportDataValidationArrayEmptyArrayAsParameter()
    {
        $this->expectException('\Knorke\Exception\KnorkeException');

        $this->fixture->importDataValidationArray(array(), $this->testGraph, $this->testGraph);
    }
}

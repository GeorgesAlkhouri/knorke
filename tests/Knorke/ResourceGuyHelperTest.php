<?php

namespace Tests\Knorke;

use Knorke\ResourceGuy;
use Knorke\ResourceGuyHelper;

class ResourceGuyHelperTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new ResourceGuyHelper(
            $this->store,
            array($this->testGraph->getUri()),
            $this->statementFactory,
            $this->nodeFactory,
            $this->rdfHelpers,
            $this->commonNamespaces
        );
    }

    /*
     * Tests for createInstanceByUri
     */

    public function testCreateInstanceByUri()
    {
        $this->importTurtle('
            @prefix foo: <http://foo/> .

            foo:1 foo:2 foo:3 .
            '
        );

        $expectedGuy = new ResourceGuy($this->commonNamespaces);
        $expectedGuy['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/1');
        $expectedGuy['http://foo/2'] = $this->nodeFactory->createNamedNode('http://foo/3');

        $this->assertEquals(
            $expectedGuy,
            $this->fixture->createInstanceByUri('http://foo/1')
        );
    }

    public function testCreateInstanceByUriWithLevels()
    {
        $this->importTurtle('
            @prefix foo: <http://foo/> .

            foo:1 foo:2 foo:3 .
            foo:3 foo:4 foo:5 .
            '
        );

        $expectedGuy = new ResourceGuy($this->commonNamespaces);
        $expectedGuy['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/1');

        // sub ResourceGuy instance
        $expectedGuy['http://foo/2'] = new ResourceGuy($this->commonNamespaces);
        $expectedGuy['http://foo/2']['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/3');
        $expectedGuy['http://foo/2']['http://foo/4'] = $this->nodeFactory->createNamedNode('http://foo/5');

        $this->assertEquals(
            $expectedGuy,
            $this->fixture->createInstanceByUri('http://foo/1', 2)
        );
    }

    public function testCreateInstanceByUriWithLevelsInArray()
    {
        $this->importTurtle('
            @prefix foo: <http://foo/> .

            foo:1 foo:2 foo:3 ;
                  foo:2 foo:5 .

            foo:3 foo:4 foo:5 .

            foo:5 foo:6 foo:7 .
            '
        );

        $expectedGuy = new ResourceGuy($this->commonNamespaces);
        $expectedGuy['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/1');

        // sub ResourceGuy instance
        $subGuy3 = new ResourceGuy($this->commonNamespaces);
        $subGuy3['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/3');
        $subGuy3['http://foo/4'] = $this->nodeFactory->createNamedNode('http://foo/5');

        // sub ResourceGuy instance
        $subGuy5 = new ResourceGuy($this->commonNamespaces);
        $subGuy5['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/5');
        $subGuy5['http://foo/6'] = $this->nodeFactory->createNamedNode('http://foo/7');

        $expectedGuy['http://foo/2'] = array($subGuy3, $subGuy5);

        $this->assertEquals(
            $expectedGuy,
            $this->fixture->createInstanceByUri('http://foo/1', 2)
        );
    }

    /*
     * Tests for getInstancesByWhereClause
     */

    public function testGetInstancesByWhereClause()
    {
        $this->importTurtle('
            @prefix foo: <http://foo/> .

            foo:1 a foo:baz ;
                  foo:3 "cool" .
            '
        );

        $subGuy = new ResourceGuy($this->commonNamespaces);
        $subGuy['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/baz');

        $expectedGuy = new ResourceGuy($this->commonNamespaces);
        $expectedGuy['_idUri'] = $this->nodeFactory->createNamedNode('http://foo/1');
        $expectedGuy[$this->commonNamespaces->getUri('rdf'). 'type'] = $subGuy;
        $expectedGuy['http://foo/3'] = $this->nodeFactory->createLiteral('cool');

        $this->assertEquals(
            array(
                $expectedGuy
            ),
            $this->fixture->getInstancesByWhereClause(
                'http://foo/baz',
                '?guy <http://foo/3> "cool"',
                2
            )
        );
    }

    /*
     * Tests for toStatements
     */

    public function testToStatements()
    {
        $this->importTurtle('
            @prefix foo: <http://foo/> .

            foo:1 foo:2 foo:3 ;
                foo:4 "fuu"@de .

            foo:a foo:b foo:c .
            '
        );

        $guy = $this->fixture->createInstanceByUri('http://foo/1');
        $guy['foaf:knows'] = $this->fixture->createInstanceByUri('http://foo/a');

        $this->assertEquals(
            array(
                // guy
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://foo/1'),
                    $this->nodeFactory->createNamedNode('http://foo/2'),
                    $this->nodeFactory->createNamedNode('http://foo/3')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://foo/1'),
                    $this->nodeFactory->createNamedNode('http://foo/4'),
                    $this->nodeFactory->createLiteral('fuu', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', 'de')
                ),
                // relation
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://foo/1'),
                    $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/knows'),
                    $this->nodeFactory->createNamedNode('http://foo/a')
                ),
                // another guy
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://foo/a'),
                    $this->nodeFactory->createNamedNode('http://foo/b'),
                    $this->nodeFactory->createNamedNode('http://foo/c')
                ),
            ),
            $this->fixture->toStatements($guy)
        );
    }
}

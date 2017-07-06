<?php

namespace Tests\Knorke;

use Knorke\Importer;
use Knorke\Exception\KnorkeException;
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
                'http://1' => 'http://2',           // x
                'http://3' => array(
                    array(
                        '_idUri' => 'http://4',     // x
                        'http://5' => '6',          // x
                        'http://7' => 'http://8'    // x
                    ),
                ),
                'http://9' => array(
                    '_idUri' => 'http://10',
                    'http://11' => 'http://12'
                )
            ),
            $startResource,
            $this->testGraph
        );

        $this->assertCountStatementIterator(6, $this->store->query($selectAll));

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
                'p' => $this->nodeFactory->createNamedNode('http://1'),
                'o' => $this->nodeFactory->createNamedNode('http://2'),
            ),
            array(
                's' => $startResource,
                'p' => $this->nodeFactory->createNamedNode('http://3'),
                'o' => $this->nodeFactory->createNamedNode('http://4'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://4'),
                'p' => $this->nodeFactory->createNamedNode('http://5'),
                'o' => $this->nodeFactory->createLiteral('6'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://4'),
                'p' => $this->nodeFactory->createNamedNode('http://7'),
                'o' => $this->nodeFactory->createNamedNode('http://8'),
            ),
            array(
                's' => $startResource,
                'p' => $this->nodeFactory->createNamedNode('http://9'),
                'o' => $this->nodeFactory->createNamedNode('http://10'),
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('http://10'),
                'p' => $this->nodeFactory->createNamedNode('http://11'),
                'o' => $this->nodeFactory->createNamedNode('http://12'),
            ),
        ));
        $expectedResult->setVariables(array('s', 'p', 'o'));

        $this->assertSetIteratorEquals(
            $expectedResult,
            $this->store->query($selectAll)
        );
    }

    /*
     * Tests for transformPhpArrayToStatementArray
     */

    public function testTransformPhpArrayToStatementArray()
    {
        $result = $this->fixture->transformPhpArrayToStatementArray(
            $this->testGraph,
            array(
                'http://1' => 'http://2',
                'http://3' => array(
                    0 => 'http://4',
                    'http://5',
                ),
                'http://6' => array(
                    // resource 1
                    'http://7',

                    // resource in list with properties and objects
                    array(
                        '_idUri' => 'http://8',
                        'http://9' => array (
                            // should force function to use this URI instead of a generated one
                            '_idUri' => 'http://10',
                            'http://11' => 'http://12'
                        )
                    )
                ),
                'http://13' => array(
                    '_idUri' => 'http://14',
                    'http://15' => '16',
                ),
            )
        );

        $this->assertEquals(
            array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://14'),
                    $this->nodeFactory->createNamedNode('http://15'),
                    $this->nodeFactory->createLiteral('16')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://10'),
                    $this->nodeFactory->createNamedNode('http://11'),
                    $this->nodeFactory->createNamedNode('http://12')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://8'),
                    $this->nodeFactory->createNamedNode('http://9'),
                    $this->nodeFactory->createNamedNode('http://10')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://1'),
                    $this->nodeFactory->createNamedNode('http://2')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://3'),
                    $this->nodeFactory->createNamedNode('http://4')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://3'),
                    $this->nodeFactory->createNamedNode('http://5')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://6'),
                    $this->nodeFactory->createNamedNode('http://7')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://6'),
                    $this->nodeFactory->createNamedNode('http://8')
                ),
                $this->statementFactory->createStatement(
                    $this->testGraph,
                    $this->nodeFactory->createNamedNode('http://13'),
                    $this->nodeFactory->createNamedNode('http://14')
                ),
            ),
            $result
        );
    }

    // test that exception is thrown for sub resource array, which has no valid property
    public function testTransformPhpArrayToStatementArrayCheckForSubResourceException()
    {
        $this->expectException('Knorke\Exception\KnorkeException');

        $result = $this->fixture->transformPhpArrayToStatementArray(
            $this->testGraph,
            array(
                /* a property URI is missing here */array(
                    '_idUri' => 'http://9',
                    'http://10' => 'http://11'
                )
            )
        );
    }
}

<?php

namespace Tests\Knorke;

use Knorke\DataBlank;
use Knorke\Store\SemanticDbl;
use Saft\Rdf\BlankNodeImpl;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class DataBlankTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->initFixture();
    }

    protected function getFixtureInstance()
    {
        return new DataBlank(
            $this->commonNamespaces,
            $this->rdfHelpers,
            $this->store,
            array($this->testGraph)
        );
    }

    protected function initFixture()
    {
        $this->fixture = $this->getFixtureInstance();
    }

    /*
     * Tests for standard data handling
     */

    public function testDataHandling()
    {
        /*
         * string in, string out
         */
        $blank = $this->getFixtureInstance();
        $blank['rdfs:label'] = 'label';
        $this->assertEquals('label', $blank['rdfs:label']);

        /*
         * expect that only latest value is used
         */
        $blank = $this->getFixtureInstance();
        $blank['rdfs:label'] = 'label1';
        $blank['rdfs:label'] = 'label2';
        $this->assertEquals(array('label1', 'label2'), $blank['rdfs:label']);

        /*
         * connect datablank instances
         */
        $blank = $this->getFixtureInstance();
        $anotherBlank = $this->getFixtureInstance();
        $blank['foaf:knows'] = $anotherBlank;
        $blank['foaf:knows']['foaf:name'] = 'cool';
        $this->assertEquals($anotherBlank, $blank['foaf:knows']);
        $this->assertEquals('cool', $blank['foaf:knows']['foaf:name']);
    }

    /*
     * Tests how it reacts if underlying store data got updated
     */

    public function testDataLoadingAfterUpdatesInUnderlyingStore()
    {
        $this->importTurtle('
            @prefix foo: <http://foo> .

            <http://s> <http://p> <http://o> .
            ',
            $this->testGraph,
            $this->store
        );

        $blank = $this->getFixtureInstance();
        $blank->initByStoreSearch('http://s');

        $expectedBlank = $this->getFixtureInstance();
        $expectedBlank['_idUri'] = 'http://s';
        $expectedBlank['http://p'] = $this->getFixtureInstance();
        $expectedBlank['http://p']['_idUri'] = 'http://o';

        $this->assertEquals($expectedBlank, $blank);

        /*
         * update store to later, if it gathers latest data
         */
        $this->store->deleteMatchingStatements($this->statementFactory->createStatement(
            $this->nodeFactory->createNamedNode('http://s'),
            $this->nodeFactory->createAnyPattern(),
            $this->nodeFactory->createAnyPattern(),
            $this->testGraph
        ));

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://new'),
                $this->testGraph
            ),
        ));

        $blank = $this->getFixtureInstance();
        $blank->initByStoreSearch('http://s');

        $expectedBlank = $this->getFixtureInstance();
        $expectedBlank['_idUri'] = 'http://s';
        $expectedBlank['http://p'] = $this->getFixtureInstance();
        $expectedBlank['http://p']['_idUri'] = 'http://new';

        $this->assertEquals($expectedBlank, $blank);
    }

    /*
     * Tests for getArrayCopy
     */

    public function testGetArrayCopy()
    {
        $this->fixture['http://foo'] = 'bar';
        $this->fixture['http://foo2'] = array(0, 2);

        $this->fixture['http://foo3'] = $this->getFixtureInstance();
        $this->fixture['http://foo3']['http://foo4'] = 4;

        $this->assertEquals(
            array(
                'http://foo' => 'bar',
                'http://foo2' => array(0, 2),
                'http://foo3' => array(
                    'http://foo4' => 4,
                )
            ),
            $this->fixture->getArrayCopy()
        );
    }

    /*
     * Tests for initByStoreQuery
     */

    // tests usage of datablanks stored as object in a datablank
    public function testInitByStoreSearchAndRecursiveDataBlankUsage()
    {
        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://o'),
                $this->nodeFactory->createNamedNode('http://p2'),
                $this->nodeFactory->createNamedNode('http://o2'),
                $this->testGraph
            )
        ));

        $dataBlank = $this->getFixtureInstance();
        $dataBlank->initByStoreSearch('http://s');

        $dataBlankToCheckAgainst = $this->getFixtureInstance();
        $dataBlankToCheckAgainst['_idUri'] = 'http://s';
        $dataBlankToCheckAgainst['http://p'] = $this->getFixtureInstance();
        // sub datablank
        $dataBlankToCheckAgainst['http://p']['_idUri'] = 'http://o';

        $this->assertEquals($dataBlank, $dataBlankToCheckAgainst);
    }

    // test case with a list of entries related to one resource, focus on array handling
    public function testInitByStoreSearchAndRecursiveDataBlankUsage2()
    {
        /*

            http://foo --- http://bar
                                    |
                                     `---- http://biz1
                                    |
                                     `---- http://biz2
         */
        $this->importTurtle('
            @prefix foo: <http://foo> .

            <http://foo> <http://bar> <http://baz1> ;
                         <http://bar> <http://baz2> .

            <http://baz1> <http://bar1> <http://biz1> .
            <http://baz2> <http://bar2> <http://biz2> .
            ',
            $this->testGraph,
            $this->store
        );

        $this->fixture = new DataBlank(
            $this->commonNamespaces,
            $this->rdfHelpers,
            $this->store,
            array($this->testGraph),
                array(
                'use_prefixed_predicates' => true,
                'use_prefixed_objects' => true,
            )
        );

        $this->fixture->initByStoreSearch('http://foo');

        $this->assertEquals(
            array(
                '_idUri' => 'http://foo',
                'http://bar' => array(
                    array(
                        '_idUri' => 'http://baz1',
                    ),
                    array(
                        '_idUri' => 'http://baz2',
                    )
                )
            ),
            $this->fixture->getArrayCopy()
        );
    }

    // tests performance on dense and complex tree
    public function testInitByStoreSearchPerformanceOnDenseTree()
    {
        $this->importTurtle(
            file_get_contents(__DIR__.'/../example-files/dense-resource-tree.ttl'),
            $this->testGraph,
            $this->store
        );

        $this->fixture = $this->getFixtureInstance();

        $this->fixture->initByStoreSearch('http://foo/1');
        $this->assertEquals(13, count($this->fixture->getArrayCopy()['http://foo/2']));
    }

    // tests if reloading further data works
    public function testInitByStoreSearchReloadFurtherData()
    {
        $this->importTurtle(
            file_get_contents(__DIR__.'/../example-files/dense-resource-tree.ttl'),
            $this->testGraph,
            $this->store
        );

        $this->fixture = $this->getFixtureInstance();

        $this->fixture->initByStoreSearch('http://foo/1');

        $this->assertTrue($this->fixture['http://foo/2'][0]['http://foo/4'] instanceof DataBlank);
        $this->assertEquals('http://foo/4', $this->fixture['http://foo/2'][0]['http://foo/4']['_idUri']);
    }

    /*
     *
     */

    // test if DataBlank instance is iterable
    public function testInstanceIsIterable()
    {
        $this->importTurtle(
            '@prefix foo: <http://foo/>.

            foo:1 foo:2 foo:3 ;
                foo:2 foo:4 .
            ',
            $this->testGraph,
            $this->store
        );

        $this->fixture = $this->getFixtureInstance();

        $this->fixture->initByStoreSearch('http://foo/1');

        $inLoop = false;

        foreach ($this->fixture as $key => $value) {
            $inLoop = true;
            $this->assertTrue('_idUri' == $key  || 'http://foo/2' == $key);
            $this->assertTrue(is_string($value) || is_array($value));

            if (is_array($value)) {
                $this->assertEquals(2, count($value));

                $expected = array(
                    $this->getFixtureInstance(),
                    $this->getFixtureInstance()
                );
                $expected[0]['_idUri'] = 'http://foo/3';
                $expected[1]['_idUri'] = 'http://foo/4';

                $this->assertEquals($expected, $value);
            } else {
                $this->assertEquals('http://foo/1', $value);
            }
        }

        $this->assertTrue($inLoop);
    }
}

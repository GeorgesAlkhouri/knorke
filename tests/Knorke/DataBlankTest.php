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
        return new DataBlank($this->commonNamespaces, $this->rdfHelpers);
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

    // test that getting http://...#label results in rdfs:label property is set as well
    public function testGetterMagic()
    {
        $blank = $this->getFixtureInstance();
        $blank['rdfs:label'] = 'label';
        $this->assertEquals($blank->get('http://www.w3.org/2000/01/rdf-schema#label'), 'label');

        $blank = $this->getFixtureInstance();
        $blank['http://www.w3.org/2000/01/rdf-schema#label'] = 'label';
        $this->assertEquals($blank->get('rdfs:label'), 'label');
    }

    /*
     * Tests how it reacts if underlying store data got updated
     */

    public function testDataLoadingAfterUpdatesInUnderlyingStore()
    {
        $subjectNode = $this->nodeFactory->createNamedNode('http://s');

        $this->store->createGraph($this->testGraph);

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $subjectNode,
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o'),
                $this->testGraph
            ),
        ));

        $blank = $this->getFixtureInstance();
        $blank->initByStoreSearch($this->store, $this->testGraph, 'http://s');

        $this->assertEquals('http://o', $blank['http://p']);

        /*
         * update store to later, if it gathers latest data
         */
        $this->store->deleteMatchingStatements($this->statementFactory->createStatement(
            $subjectNode,
            $this->nodeFactory->createAnyPattern(),
            $this->nodeFactory->createAnyPattern(),
            $this->testGraph
        ));

        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $subjectNode,
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://new'),
                $this->testGraph
            ),
        ));

        $blank = $this->getFixtureInstance();
        $blank->initByStoreSearch($this->store, $this->testGraph, 'http://s');

        $this->store->dropGraph($this->testGraph);

        $this->assertEquals('http://new', $blank['http://p']);
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
        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

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

        $dataBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        $dataBlank->initByStoreSearch($this->store, $this->testGraph, 'http://s');

        $dataBlankToCheckAgainst = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        $dataBlankToCheckAgainst['_idUri'] = 'http://s';
        $dataBlankToCheckAgainst['http://p'] = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        // sub datablank
        $dataBlankToCheckAgainst['http://p']['_idUri'] = 'http://o';
        $dataBlankToCheckAgainst['http://p']['http://p2'] = 'http://o2';

        $this->assertEquals($dataBlank, $dataBlankToCheckAgainst);
    }

    public function testInitByStoreSearchAndRecursiveDataBlankUsageWithBlankNode1()
    {
        $this->importTurtle('
            @prefix back: <http://back/model/> .

            <http://s> <http://p> <http://o> ;
                        <http://p> <http://o-standalone> ;
                        <http://p> "o-standalone" ;
                        <http://p> [
                            <http://p2> <http://o2>
                        ] .

            <http://o> <http://p3> <http://o3> .
            ',
            $this->testGraph,
            $this->store
        );

        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $this->fixture->initByStoreSearch($this->store, $this->testGraph, 'http://s');

        // get blank node id
        $result = $this->store->query('SELECT * WHERE { ?s <http://p2> <http://o2>. }');
        foreach ($result as $value) { $blankNodeId = $value['s']->toNQuads(); break; }

        // TODO the following merges two structures which have to be separate!
        //      if more time, investigate if hardf turtle parser is faulty or our implementation
        //      expected behavior: p2--o2 and p3--o3 are separate!
        $this->assertEquals(
            array(
                '_idUri' => 'http://s',
                'http://p' => array(
                    array(
                        '_idUri' => $this->fixture->getArrayCopy()['http://p'][0]['_idUri'],
                        'http://p3' => 'http://o3'
                    ),
                    'http://o-standalone',
                    'o-standalone',
                    array(
                        '_idUri' => $this->fixture->getArrayCopy()['http://p'][3]['_idUri'],
                        'http://p2'=> 'http://o2',
                        'http://p3'=> 'http://o3'
                    ),
                )
            ),
            $this->fixture->getArrayCopy()
        );
    }

    // test special case with sub blank nodes. thats a regression check, because we expirienced
    // it only in a productive environment.
    public function testInitByStoreSearchAndRecursiveDataBlankUsageWithBlankNode2()
    {
        $this->commonNamespaces->add('backdata', 'http://back/data/');
        $this->commonNamespaces->add('backmodel', 'http://back/model/');

        $this->importTurtle('
            @prefix back: <http://back/model/> .

            <http://back/data/Wacken2017> <http://rdf#type> <http://back/model/Event> ;
                <http://rdfs#label> "Wacken" .

            <http://back/data/user1> <http://rdf#type> <http://back/model/User> ;
                <http://back/model/has-rights> [
                    <http://back/model/type> <http://back/model/Event> ;
                    <http://back/model/event> <http://back/data/Wacken2017>
                ] .
            ',
            $this->testGraph,
            $this->store
        );

        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $this->fixture->initByStoreSearch($this->store, $this->testGraph, 'http://back/data/user1');

        $this->assertEquals(
            array(
                '_idUri' => 'http://back/data/user1',
                'http://rdf#type' => 'backmodel:User',
                'backmodel:has-rights' => array(
                    // get randomly generated blank node
                    '_idUri' => $this->fixture->getArrayCopy()['backmodel:has-rights']['_idUri'],
                    'backmodel:type' => 'backmodel:Event',
                    'backmodel:event' => array(
                        '_idUri' => 'http://back/data/Wacken2017',
                        'http://rdf#type' => 'backmodel:Event',
                        'http://rdfs#label' => 'Wacken'
                    )
                )
            ),
            $this->fixture->getArrayCopy()
        );
    }

    // test case with a list of entries related to one resource, focus on array handling
    public function testInitByStoreSearchAndRecursiveDataBlankUsageWithBlankNode3()
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

        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $this->fixture->initByStoreSearch($this->store, $this->testGraph, 'http://foo');

        $this->assertEquals(
            array(
                '_idUri' => 'http://foo',
                'http://bar' => array(
                    array(
                        '_idUri' => 'http://baz1',
                        'http://bar1' => 'http://biz1'
                    ),
                    array(
                        '_idUri' => 'http://baz2',
                        'http://bar2' => 'http://biz2'
                    )
                )
            ),
            $this->fixture->getArrayCopy()
        );
    }
}

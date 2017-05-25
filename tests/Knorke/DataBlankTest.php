<?php

namespace Tests\Knorke;

use Knorke\DataBlank;
use Knorke\InMemoryStore;
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
     * Tests for initBySetResult
     */

    // test init process to only read what is really relevant
    public function testClearSeparatedStuffBySubject()
    {
        $result = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('stat:1'),
                'p' => $this->nodeFactory->createNamedNode('kno:computationOrder'),
                'o' => $this->nodeFactory->createBlankNode('genid1')
            ),
            array(
                's' => $this->nodeFactory->createBlankNode('genid1'),
                'p' => $this->nodeFactory->createNamedNode('kno:_0'),
                'o' => $this->nodeFactory->createLiteral('[stat:2]*2')
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('stat:2'),
                'p' => $this->nodeFactory->createNamedNode('rdf:type'),
                'o' => $this->nodeFactory->createNamedNode('kno:StatisticValue')
            ),
            array(
                's' => $this->nodeFactory->createNamedNode('stat:2'),
                'p' => $this->nodeFactory->createNamedNode('rdfs:label'),
                'o' => $this->nodeFactory->createLiteral('Statistic Value 2')
            ),
        ));
        $result->setVariables('s', 'p', 'o');

        $this->fixture->initBySetResult($result, 'stat:2');

        $this->assertEquals(
            array(
                'rdf:type' => 'kno:StatisticValue',
                'rdfs:label' => 'Statistic Value 2',
                '_idUri' => 'stat:2'
            ),
            $this->fixture->getArrayCopy()
        );
    }

    public function testNoPrefixedPredicateAndObject()
    {
        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => false,
            'use_prefixed_objects' => false,
        ));

        $result = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('rdfs:label'),
                'o' => $this->nodeFactory->createLiteral('Label for s'),
            ),
            array(
                's' => $this->nodeFactory->createBlankNode('blank'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
            )
        ));

        $this->fixture->initBySetResult($result, 'http://s');

        $this->assertEquals(
            array(
                'http://www.w3.org/2000/01/rdf-schema#label' => 'Label for s',
                '_idUri' => 'http://s'
            ),
            $this->fixture->getArrayCopy()
        );
    }

    public function testPrefixedPredicate()
    {
        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => false,
        ));

        $result = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('rdfs:label'),
                'o' => $this->nodeFactory->createLiteral('Label for s'),
            ),
            array(
                's' => $this->nodeFactory->createBlankNode('blank'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
            )
        ));

        $this->fixture->initBySetResult($result, 'http://s');

        $this->assertEquals(
            array(
                'rdfs:label' => 'Label for s',
                '_idUri' => 'http://s'
            ),
            $this->fixture->getArrayCopy()
        );
    }

    public function testPrefixedPredicateAndObject()
    {
        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $result = new SetResultImpl(array(
            array(
                's' => $this->nodeFactory->createNamedNode('http://s'),
                'p' => $this->nodeFactory->createNamedNode('rdfs:label'),
                'o' => $this->nodeFactory->createLiteral('Label for s'),
            ),
            array(
                's' => $this->nodeFactory->createBlankNode('blank'),
                'p' => $this->nodeFactory->createNamedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => $this->nodeFactory->createNamedNode('http://xmlns.com/foaf/0.1/Person'),
            )
        ));

        $this->fixture->initBySetResult($result, 'http://s');

        $this->assertEquals(
            array(
                'rdfs:label' => 'Label for s',
                '_idUri' => 'http://s'
            ),
            $this->fixture->getArrayCopy()
        );
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

    public function testInitByStoreSearchAndRecursiveDataBlankUsageWithBlankNode()
    {
        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createBlankNode('genid1'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createNamedNode('http://o-standalone'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://s'),
                $this->nodeFactory->createNamedNode('http://p'),
                $this->nodeFactory->createLiteral('o-standalone'),
                $this->testGraph
            ),
            // sub datablank 1 (blanknode as subject)
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('http://p2'),
                $this->nodeFactory->createNamedNode('http://o2'),
                $this->testGraph
            ),
            // sub datablank 2 (namednode as subject)
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://o'),
                $this->nodeFactory->createNamedNode('http://p3'),
                $this->nodeFactory->createNamedNode('http://o3'),
                $this->testGraph
            )
        ));

        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $this->fixture->initByStoreSearch($this->store, $this->testGraph, 'http://s');

        // get blank node id
        $result = $this->store->query('SELECT * WHERE { ?s <http://p2> <http://o2>. }');
        foreach ($result as $value) { $blankNodeId = $value['s']->toNQuads(); break; }

        $this->assertEquals(
            array(
                '_idUri' => 'http://s',
                'http://p' => array(
                    // sub datablank 1
                    array(
                        '_idUri' => $blankNodeId,
                        'http://p2' => 'http://o2'
                    ),
                    // sub datablank 2
                    array(
                        '_idUri' => 'http://o',
                        'http://p3'=> 'http://o3'
                    ),
                    'http://o-standalone',
                    'o-standalone'
                )
            ),
            $this->fixture->getArrayCopy()
        );
    }

    // test special case with sub blank nodes. thats a regression check, because we expierenced
    // it only in a productive environment.
    public function testInitByStoreSearchAndRecursiveDataBlankUsageWithBlankNode2()
    {
        /*
            > DataBlank implementation produced something like:

            array(
                array(
                    'backmodel:Event' => array(
                        '_idUri' => array(      <====== this makes no sense

                            '_idUri' => 'http://back/data/Wacken2017',
                            'rdf:type' => 'backmodel:Event',
                            'rdfs:label' => 'Wacken'
                        ),
                        'rdf:type' => 'backmodel:Event',
                        'rdfs:label' => 'Wacken'
                    ),
                    'backmodel:type' => 'backmodel:Event',
                ),
                ...
            )

            > According n-triples:

            <http://back/data/Wacken2017> <http://rdf#type> <http://back/model/Event> .
            <http://back/data/Wacken2017> <http://rdfs#label> "Wacken" .
            <http://back/data/user1> <http://rdf#type> <http://back/model/User> .
            <http://back/data/user1> <http://back/model/has-rights> _:genid1 .
            _:genid1 <http://back/model/type> <http://back/model/Event> .
            _:genid1 <http://back/model/event> <http://back/data/Wacken2017> .

         */
        $this->commonNamespaces->add('backdata', 'http://back/data/');
        $this->commonNamespaces->add('backmodel', 'http://back/model/');

        $this->store->addStatements(array(
            // <http://back/data/Wacken2017> <http://rdf#type> <http://back/model/Event> .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://back/data/Wacken2017'),
                $this->nodeFactory->createNamedNode('http://rdf#type'),
                $this->nodeFactory->createNamedNode('http://back/model/Event'),
                $this->testGraph
            ),
            // <http://back/data/Wacken2017> <http://rdfs#label> "Wacken" .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://back/data/Wacken2017'),
                $this->nodeFactory->createNamedNode('http://rdfs#label'),
                $this->nodeFactory->createLiteral('Wacken'),
                $this->testGraph
            ),
            // <http://back/data/user1> <http://rdf#type> <http://back/model/User> .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://back/data/user1'),
                $this->nodeFactory->createNamedNode('http://rdf#type'),
                $this->nodeFactory->createNamedNode('http://back/model/User'),
                $this->testGraph
            ),
            // <http://back/data/user1> <http://back/model/has-rights> _:genid1 .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://back/data/user1'),
                $this->nodeFactory->createNamedNode('http://back/model/has-rights'),
                $this->nodeFactory->createBlankNode('genid1'),
                $this->testGraph
            ),
            // _:genid1 <http://back/model/type> <http://back/model/Event> .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('http://back/model/type'),
                $this->nodeFactory->createLiteral('http://back/model/Event'),
                $this->testGraph
            ),
            // _:genid1 <http://back/model/event> <http://back/data/Wacken2017> .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('http://back/model/event'),
                $this->nodeFactory->createNamedNode('http://back/data/Wacken2017'),
                $this->testGraph
            )
        ));

        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $this->fixture->initByStoreSearch($this->store, $this->testGraph, 'http://back/data/user1');

        // get blank node ids
        $result = $this->store->query('SELECT * WHERE { ?s <http://back/model/type> <http://back/model/Event>. }');
        foreach ($result as $value) { $blankNodeId1 = $value['s']->toNQuads(); break; }

        $this->assertEquals(
            array(
                '_idUri' => 'http://back/data/user1',
                'http://rdf#type' => 'backmodel:User',
                'backmodel:has-rights' => array(
                    '_idUri' => $blankNodeId1,
                    'backmodel:type' => 'http://back/model/Event',
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

            http://foo --- http://bar --- http://baz
                                                |
                                                 `---- http://bar --- http://biz1
                                                |
                                                 `---- http://bar --- http://biz2

         */
        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createNamedNode('http://bar'),
                $this->nodeFactory->createNamedNode('http://baz1'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->nodeFactory->createNamedNode('http://bar'),
                $this->nodeFactory->createNamedNode('http://baz2'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://baz1'),
                $this->nodeFactory->createNamedNode('http://bar1'),
                $this->nodeFactory->createNamedNode('http://biz1'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://baz2'),
                $this->nodeFactory->createNamedNode('http://bar2'),
                $this->nodeFactory->createNamedNode('http://biz2'),
                $this->testGraph
            ),
        ));

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

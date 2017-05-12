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

        $this->fixture = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
    }

    // test that getting http://...#label results in rdfs:label property is set as well
    public function testGetterMagic()
    {
        $blank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        $blank['rdfs:label'] = 'label';
        $this->assertEquals($blank->get('http://www.w3.org/2000/01/rdf-schema#label'), 'label');

        $blank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
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

        $this->fixture['http://foo3'] = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
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
}

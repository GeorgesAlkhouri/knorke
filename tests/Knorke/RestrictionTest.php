<?php

namespace Tests\Knorke;

use Knorke\Restriction;

class RestrictionTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new Restriction(
            $this->commonNamespaces,
            $this->rdfHelpers,
            $this->dataBlankHelper,
            $this->store,
            $this->testGraph
        );
    }

    public function testNoPrefixedPredicateAndObject()
    {
        /*
        http://resourceWithRestrictions
            rdfs:label "label"@de ;
            kno:restriction-one-of http://foo , http://bar ;
            kno:inherits-all-properties-of http://foreign-resource .

        http://foreign-resource kno:restriction-order [
                kno:_0 http://foo ;
                kno:_1 http://bar
            ] .
        */
        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('label'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('kno:restriction-one-of'),
                $this->nodeFactory->createNamedNode('http://foo'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('kno:restriction-one-of'),
                $this->nodeFactory->createNamedNode('http://bar'),
                $this->testGraph
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('kno:inherits-all-properties-of'),
                $this->nodeFactory->createNamedNode('http://foreign-resource'),
                $this->testGraph
            ),
            /*
             * http://foreign-resource
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foreign-resource'),
                $this->nodeFactory->createNamedNode('kno:restriction-order'),
                $this->nodeFactory->createBlankNode('genid1')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_0'),
                $this->nodeFactory->createNamedNode('http://foo')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createBlankNode('genid1'),
                $this->nodeFactory->createNamedNode('kno:_1'),
                $this->nodeFactory->createNamedNode('http://bar')
            ),
        ));

        // get namespace URI shortcuts
        $nsKno = $this->commonNamespaces->getUri('kno');
        $nsRdf = $this->commonNamespaces->getUri('rdf');

        // get blank node ID, because blank nodes gets stored with a random ID to avoid collissions
        $result = $this->store->query('SELECT * WHERE { ?s <'. $nsKno .'_0> <http://foo> . }');
        $blankNodeId = array_keys($result->getArrayCopy())[0];

        $restrictions = $this->fixture->getRestrictionsForResource('http://resourceWithRestrictions');
        $restArry = $restrictions->getArrayCopy();

        $this->assertEquals(
            array(
                'rdfs:label' => 'label',
                'kno:restriction-one-of' => array(
                    0 => 'http://foo',
                    1 => 'http://bar',
                ),
                'kno:inherits-all-properties-of' => array(
                    '_idUri' => 'http://foreign-resource',
                    'kno:restriction-order' => array(
                        'kno:_0' => 'http://foo',
                        'kno:_1' => 'http://bar',
                        '_idUri' => $restArry['kno:inherits-all-properties-of']['kno:restriction-order']['_idUri'],
                    ),
                ),
                'kno:restriction-order' => array(
                    'kno:_0' => 'http://foo',
                    'kno:_1' => 'http://bar',
                    '_idUri' => $restArry['kno:restriction-order']['_idUri'],
                ),
                '_idUri' => 'http://resourceWithRestrictions'
            ),
            $restArry
        );
    }
}

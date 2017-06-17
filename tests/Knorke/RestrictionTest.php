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
            array($this->testGraph)
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
                $this->nodeFactory->createNamedNode('http://foobarrrrrr')
            ),
        ));

        // get namespace URI shortcuts
        $nsKno = $this->commonNamespaces->getUri('kno');
        $nsRdf = $this->commonNamespaces->getUri('rdf');

        $restrictions = $this->fixture->getRestrictionsForResource('http://resourceWithRestrictions');
        $restArry = $restrictions->getArrayCopy();

        $this->assertEquals(
            array(
                'rdfs:label' => 'label',
                'kno:restriction-one-of' => array(
                    array(
                        '_idUri' => 'http://foo'
                    ),
                    array(
                        '_idUri' => 'http://bar'
                    )
                ),
                'kno:inherits-all-properties-of' => array(
                    '_idUri' => 'http://foreign-resource'
                ),
                'kno:restriction-order' => array(
                    '_idUri' => 'http://foobarrrrrr'
                ),
                '_idUri' => 'http://resourceWithRestrictions'
            ),
            $restArry
        );
    }
}

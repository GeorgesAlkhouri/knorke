<?php

namespace Tests\Knorke;

use Knorke\InMemoryStore;
use Knorke\Restriction;
use Saft\Rdf\BlankNodeImpl;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\LiteralImpl;
use Saft\Rdf\NamedNodeImpl;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\NodeUtils;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class RestrictionTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new Restriction($this->store, $this->commonNamespaces, $this->rdfHelpers);
    }

    public function testNoPrefixedPredicateAndObject()
    {
        /*
        http://resourceWithRestrictions
            rdfs:label "label"@de ;
            kno:restrictionOneOf http://foo , http://bar ;
            kno:inheritsAllPropertiesOf http://foreign-resource .

        http://foreign-resource kno:restrictionOrder [
                kno:_0 http://foo ;
                kno:_1 http://bar
            ] .
        */
        $this->store->addStatements(array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('rdfs:label'),
                $this->nodeFactory->createLiteral('label')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('kno:restrictionOneOf'),
                $this->nodeFactory->createNamedNode('http://foo')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('kno:restrictionOneOf'),
                $this->nodeFactory->createNamedNode('http://bar')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://resourceWithRestrictions'),
                $this->nodeFactory->createNamedNode('kno:inheritsAllPropertiesOf'),
                $this->nodeFactory->createNamedNode('http://foreign-resource')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://foreign-resource'),
                $this->nodeFactory->createNamedNode('kno:restrictionOrder'),
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

        $restrictions = $this->fixture->getRestrictionsForResource('http://resourceWithRestrictions');

        $this->assertEquals(
            array(
                'rdfs:label' => 'label',
                'kno:restrictionOneOf' => array(
                    'http://foo',
                    'http://bar',
                ),
                'kno:inheritsAllPropertiesOf' => 'http://foreign-resource',
                'kno:restrictionOrder' => array(
                    'kno:_0' => 'http://foo',
                    'kno:_1' => 'http://bar',
                    '_idUri' => '_:genid1'
                ),
                '_idUri' => 'http://foreign-resource'
            ),
            $restrictions->getArrayCopy()
        );
    }
}

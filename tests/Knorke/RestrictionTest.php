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
    protected $nodeUtils;

    public function setUp()
    {
        parent::setUp();

        $this->store = new InMemoryStore(
            new NodeFactoryImpl($this->nodeUtils),
            new StatementFactoryImpl(),
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl(),
            $this->commonNamespaces
        );
    }

    protected function initFixture()
    {
        $this->fixture = new Restriction($this->store, $this->commonNamespaces, $this->nodeUtils);
        return $this->fixture;
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
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://resourceWithRestrictions'),
                new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                new LiteralImpl($this->nodeUtils, 'label')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://resourceWithRestrictions'),
                new NamedNodeImpl($this->nodeUtils, 'kno:restrictionOneOf'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://resourceWithRestrictions'),
                new NamedNodeImpl($this->nodeUtils, 'kno:restrictionOneOf'),
                new NamedNodeImpl($this->nodeUtils, 'http://bar')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://resourceWithRestrictions'),
                new NamedNodeImpl($this->nodeUtils, 'kno:inheritsAllPropertiesOf'),
                new NamedNodeImpl($this->nodeUtils, 'http://foreign-resource')
            ),
            new StatementImpl(
                new NamedNodeImpl($this->nodeUtils, 'http://foreign-resource'),
                new NamedNodeImpl($this->nodeUtils, 'kno:restrictionOrder'),
                new BlankNodeImpl('genid1')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                new NamedNodeImpl($this->nodeUtils, 'http://foo')
            ),
            new StatementImpl(
                new BlankNodeImpl('genid1'),
                new NamedNodeImpl($this->nodeUtils, 'kno:_1'),
                new NamedNodeImpl($this->nodeUtils, 'http://bar')
            ),
        ));

        $restrictions = $this->initFixture()->getRestrictionsForResource('http://resourceWithRestrictions');

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

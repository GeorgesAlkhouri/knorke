<?php

namespace Tests\Knorke;

use Knorke\CommonNamespaces;
use Knorke\DataBlank;
use Saft\Rdf\BlankNodeImpl;
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

class DataBlankTest extends UnitTestCase
{
    protected $nodeUtils;

    public function setUp()
    {
        $this->nodeUtils = new NodeUtils();

        $this->fixture = new DataBlank(new CommonNamespaces());
    }

    public function testPrefixedPredicate()
    {
        $this->fixture = new DataBlank(new CommonNamespaces(), array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => false,
        ));

        $result = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Label for s'),
            ),
            array(
                's' => new BlankNodeImpl('blank'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://xmlns.com/foaf/0.1/Person'),
            )
        ));

        $this->fixture->initBySetResult($result, 'http://s');

        $this->assertEquals(
            array(
                'rdf:type' => 'http://xmlns.com/foaf/0.1/Person',
                'rdfs:label' => 'Label for s'
            ),
            $this->fixture->getArrayCopy()
        );
    }

    public function testPrefixedPredicateAndObject()
    {
        $this->fixture = new DataBlank(new CommonNamespaces(), array(
            'use_prefixed_predicates' => true,
            'use_prefixed_objects' => true,
        ));

        $result = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'http://s'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Label for s'),
            ),
            array(
                's' => new BlankNodeImpl('blank'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'http://xmlns.com/foaf/0.1/Person'),
            )
        ));

        $this->fixture->initBySetResult($result, 'http://s');

        $this->assertEquals(
            array(
                'rdf:type' => 'foaf:Person',
                'rdfs:label' => 'Label for s'
            ),
            $this->fixture->getArrayCopy()
        );
    }
}

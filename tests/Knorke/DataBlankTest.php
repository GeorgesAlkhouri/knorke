<?php

namespace Tests\Knorke;

use Knorke\CommonNamespaces;
use Knorke\DataBlank;
use Knorke\InMemoryStore;
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
    protected $commonNamespaces;
    protected $nodeUtils;

    public function setUp()
    {
        $this->commonNamespaces = new CommonNamespaces();
        $this->nodeUtils = new NodeUtils();

        $this->fixture = new DataBlank(new CommonNamespaces());
        $this->store = new InMemoryStore(
            new NodeFactoryImpl($this->nodeUtils),
            new StatementFactoryImpl(),
            new QueryFactoryImpl($this->nodeUtils, new QueryUtils()),
            new StatementIteratorFactoryImpl(),
            new CommonNamespaces()
        );
    }

    // test init process to only read what is really relevant
    public function testClearSeparatedStuffBySubject()
    {
        $result = new SetResultImpl(array(
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'stat:1'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'kno:computationOrder'),
                'o' => new BlankNodeImpl('genid1')
            ),
            array(
                's' => new BlankNodeImpl('genid1'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'kno:_0'),
                'o' => new LiteralImpl($this->nodeUtils, '[stat:2]*2')
            ),
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'rdf:type'),
                'o' => new NamedNodeImpl($this->nodeUtils, 'kno:StatisticValue')
            ),
            array(
                's' => new NamedNodeImpl($this->nodeUtils, 'stat:2'),
                'p' => new NamedNodeImpl($this->nodeUtils, 'rdfs:label'),
                'o' => new LiteralImpl($this->nodeUtils, 'Statistic Value 2')
            ),
        ));
        $result->setVariables('s', 'p', 'o');

        $this->fixture->initBySetResult($result, 'stat:2');

        $this->assertEquals(
            array(
                'rdf:type' => 'kno:StatisticValue',
                'rdfs:label' => 'Statistic Value 2',
            ),
            $this->fixture->getArrayCopy()
        );
    }

    public function testGetterMagic()
    {
        $blank = new DataBlank($this->commonNamespaces);
        $blank['rdfs:label'] = 'label';
        $this->assertEquals($blank->get('http://www.w3.org/2000/01/rdf-schema#label'), 'label');

        $blank = new DataBlank($this->commonNamespaces);
        $blank['http://www.w3.org/2000/01/rdf-schema#label'] = 'label';
        $this->assertEquals($blank->get('rdfs:label'), 'label');
    }

    public function testNoPrefixedPredicateAndObject()
    {
        $this->fixture = new DataBlank($this->commonNamespaces, array(
            'use_prefixed_predicates' => false,
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
                'http://www.w3.org/2000/01/rdf-schema#label' => 'Label for s'
            ),
            $this->fixture->getArrayCopy()
        );
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
                'rdfs:label' => 'Label for s'
            ),
            $this->fixture->getArrayCopy()
        );
    }
}

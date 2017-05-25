<?php

namespace Tests\Knorke\Store;

use Knorke\Store\InMemoryStore;
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
use Tests\Knorke\UnitTestCase;

class InMemoryStoreTest extends AbstractStatementStoreTest
{
    protected function initFixture()
    {
        $this->fixture = new InMemoryStore(
           $this->nodeFactory,
           $this->statementFactory,
           $this->queryFactory,
           $this->statementIteratorFactory,
           $this->commonNamespaces,
           $this->rdfHelpers
       );

       return $this->fixture;
    }

    public function testDeleteMatchingStatements()
    {
        $this->markTestIncomplete('InMemoryStore has no advanced implementation for statement deletion, yet.');
    }
}

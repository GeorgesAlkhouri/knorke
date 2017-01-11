<?php

namespace Knorke;

use Saft\Store\BasicTriplePatternStore;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIteratorFactory;
use Saft\Sparql\Query\QueryFactory;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class InMemoryStore extends BasicTriplePatternStore
{
    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var StatementFactory
     */
    protected $statementFactory;

    /**
     * @var StatementIteratorFactory
     */
    protected $statementIteratorFactory;

    /**
     * Constructor.
     *
     * @param NodeFactory              $nodeFactory Instance of NodeFactory.
     * @param StatementFactory         $statementFactory Instance of StatementFactory.
     * @param QueryFactory             $queryFactory Instance of QueryFactory.
     * @param StatementIteratorFactory $statementIteratorFactory Instance of StatementIteratorFactory.
     */
    public function __construct(
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        QueryFactory $queryFactory,
        StatementIteratorFactory $statementIteratorFactory
    ) {
        parent::__construct($nodeFactory, $statementFactory, $queryFactory, $statementIteratorFactory);

        $this->nodeFactory = $nodeFactory;
        $this->queryFactory = $queryFactory;
        $this->statementFactory = $statementFactory;
        $this->statementIteratorFactory = $statementIteratorFactory;
        $this->queryUtils = new QueryUtils();
    }

    /**
     * This method sends a SPARQL query to the store.
     *
     * @param  string $query The SPARQL query to send to the store.
     * @param  array $options It contains key-value pairs and should provide additional introductions for the store
     *                        and/or its adapter(s) (optional).
     * @return Result Returns result of the query. Its type depends on the type of the query.
     * @throws \Exception If query is no string, is malformed or an execution error occured.
     */
    public function query($query, array $options = array())
    {
        $queryObject = $this->queryFactory->createInstanceByQueryString($query);

        if ('selectQuery' == $this->queryUtils->getQueryType($query)) {
            $queryParts = $queryObject->getQueryParts();
            $triplePattern = $queryParts['triple_pattern'];

            // handle ?s ?p ?o
            if (1 == count($triplePattern)
                && 'var' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                $setEntries = array();
                foreach ($this->statements['http://saft/defaultGraph/'] as $stmt) {
                    $setEntries[] = array(
                        $triplePattern[0]['s'] => $stmt->getSubject(),
                        $triplePattern[0]['p'] => $stmt->getPredicate(),
                        $triplePattern[0]['o'] => $stmt->getObject()
                    );
                }
                $result = new SetResultImpl($setEntries);
                $result->setVariables($queryParts['variables']);
                return $result;

            // handle ?s ?p ?o
            //        ?s rdf:type foaf:Person
            } elseif (2 == count($triplePattern)
                // ?s ?p ?o.
                && 'var' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']
                // ?s rdf:type foaf:Person.
                && 'var' == $triplePattern[1]['s_type']
                && 'uri' == $triplePattern[1]['p_type']
                && 'uri' == $triplePattern[1]['o_type']) {

                $relevantS = array();

                // ignore first pattern because it casts variables on s, p and o

                // 1. check which s has wanted p and o
                foreach ($this->statements['http://saft/defaultGraph/'] as $statement) {
                    $s = $statement->getSubject();
                    $p = $statement->getPredicate();
                    $o = $statement->getObject();
                    if ($p->getUri() == $triplePattern[1]['p']
                        && $o->isNamed() && $o->getUri() == $triplePattern[1]['o']) {
                        $relevantS[$s->getUri()] = $s;
                    }
                }

                $setEntries = array();

                // 2. get all p and o for collected s
                foreach ($this->statements['http://saft/defaultGraph/'] as $statement) {
                    $s = $statement->getSubject();
                    $p = $statement->getPredicate();
                    $o = $statement->getObject();

                    if (isset($relevantS[$s->getUri()])) {
                        $setEntries[] = array(
                            $triplePattern[0]['s'] => $s,
                            $triplePattern[0]['p'] => $p,
                            $triplePattern[0]['o'] => $o
                        );
                    }
                }

                $result = new SetResultImpl($setEntries);
                $result->setVariables($queryParts['variables']);
                return $result;
            }
        }
        return parent::query($query, $options);
    }
}

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
     * Workaround for faulty Saft\Sparql\Query\AbstractQuery functions to extract filter properly. We are only interested
     * in certain filters for TitleHelper use case.
     */
    public function getFiltersIfAvailable($queryParts)
    {
        preg_match_all('/FILTER\s\((\?[a-z]+\s*=\s*[a-z:\/<>]+.*?)\)/si', $queryParts['where'], $matches);

        if (false == isset($matches[1][0])) {
            return null;
        }

        $entries = explode('||', $matches[1][0]);

        /*
         * determine variable position (s, p or o)
         */
        preg_match('/\?([a-z0-9]+)/', $entries[0], $matches);
        $variablePosition = -1; // we assume it is in the variable list
        foreach ($queryParts['variables'] as $key => $var) {
            if ($matches[1] == $var) {
                $variablePosition = $key;
            }
        }

        /*
         * collect possible values for the variable
         */
        $possibleValues = array();
        foreach ($entries as $key => $value) {
            // extract URIs
            preg_match('/<([a-z0-9:\/\-#_%\?\)\(]+)>/si', $value, $uris);
            if (isset($uris[1])) {
                $possibleValues[$uris[1]] = $uris[1];
            }
        }

        $positionIdentifiers = array('s', 'p', 'o');

        return array(
            'variable_position' => $variablePosition,
            'variable_letter' => $positionIdentifiers[$variablePosition],
            'possible_values' => $possibleValues
        );
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
            $setEntries = array();

            // handle ?s ?p ?o
            //        ?s rdf:type foaf:Person
            if (2 <= count($triplePattern)
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

            // handle ?s ?p ?o
            } elseif (0 < count($triplePattern)
                && 'var' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statements['http://saft/defaultGraph/'] as $stmt) {
                    $setEntries[] = array(
                        $triplePattern[0]['s'] => $stmt->getSubject(),
                        $triplePattern[0]['p'] => $stmt->getPredicate(),
                        $triplePattern[0]['o'] => $stmt->getObject()
                    );
                }
            }

            /*
             * apply filter like FILTER (?p = <http://...>) and remove statements which dont match
             */
            $filterInformation = $this->getFiltersIfAvailable($queryParts);
            if (null !== $filterInformation) {
                foreach ($setEntries as $key => $stmtArray) {
                    // remove entries which are not fit the given filters
                    $relatedNode = $stmtArray[$filterInformation['variable_letter']];
                    // we assume that the node is a named node
                    if (false == isset($filterInformation['possible_values'][$relatedNode->getUri()])) {
                        // if its node does not match with the filter requirements, remove the statement from the result
                        unset($setEntries[$key]);
                    }
                }
            }

            $result = new SetResultImpl($setEntries);
            $result->setVariables($queryParts['variables']);
            return $result;
        }
        return parent::query($query, $options);
    }
}

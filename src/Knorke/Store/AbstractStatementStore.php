<?php

namespace Knorke\Store;

use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIteratorFactory;
use Saft\Sparql\Query\QueryFactory;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;
use Saft\Store\Store;

abstract class AbstractStatementStore implements Store
{
    /**
     * @var CommonNamespaces
     */
    protected $commonNamespaces;

    /**
     * @var string
     */
    protected $defaultGraphUri = 'http://knorke/defaultGraph/';

    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var RdfHelpers
     */
    protected $rdfHelpers;

    /**
     * @var StatementFactory
     */
    protected $statementFactory;

    /**
     * @var StatementIteratorFactory
     */
    protected $statementIteratorFactory;

    public function __construct(
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        QueryFactory $queryFactory,
        StatementIteratorFactory $statementIteratorFactory,
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
        $this->queryFactory = $queryFactory;
        $this->statementFactory = $statementFactory;
        $this->statementIteratorFactory = $statementIteratorFactory;
        $this->statementsPerGraph = array($this->defaultGraphUri => array());
        $this->rdfHelpers = $rdfHelpers;
    }

    /**
     * @return string Generated blank node ID hash.
     */
    public function generateBlankIdHash() : string
    {
        return hash('sha512', microtime() . rand(0, time()));
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
     * It gets all statements of a given graph which match the following conditions:
     * - statement's subject is either equal to the subject of the same statement of the graph or
     *   it is null.
     * - statement's predicate is either equal to the predicate of the same statement of the graph or
     *   it is null.
     * - statement's object is either equal to the object of a statement of the graph or it is null.
     *
     * @param Statement $statement It can be either a concrete or pattern-statement.
     * @param Node      $graph     Overrides target graph. If set, you will get all matching
     *                             statements of that graph. (optional)
     * @param array     $options   It contains key-value pairs and should provide additional
     *                             introductions for the store and/or its adapter(s). (optional)
     * @return StatementIterator It contains Statement instances  of all matching statements of
     *                           the given graph.
     * @throws \Exception because its not implemented yet
     */
    public function getMatchingStatements(
        Statement $statement,
        Node $graph = null,
        array $options = array()
    ) {
        throw new \Exception('Not implemented yet. Use query function.');
    }

    /**
     * @param Node $node
     * @throws \Exception on unknown Node type
     */
    protected function getNodeValue(Node $node)
    {
        if ($node->isConcrete()) {
            // uri
            if ($node->isNamed()) {
                $value = $node->getUri();
            // literal
            } elseif ($node->isLiteral()) {
                $value = $node->getValue();
            // blanknode
            } elseif ($node->isBlank()) {
                $value = $node->getBlankId();
            } else {
                throw new \Exception('Unknown Node type given');
            }

        } else { // anypattern
            $value = (string)$node;
        }

        return $value;
    }

    /**
     * Has no function and returns an empty array.
     *
     * @return array Empty array
     */
    public function getStoreDescription()
    {
        return array();
    }

    /**
     * This method sends a SPARQL query to the store.
     *
     * @param string $query   The SPARQL query to send to the store.
     * @param array  $options It contains key-value pairs and should provide additional introductions for the
     *                        store and/or its adapter(s). (optional)
     * @return Result Returns result of the query. Its type depends on the type of the query.
     * @throws \Exception If query is no string, is malformed or an execution error occured.
     */
    public function query($query, array $options = array())
    {
        $queryObject = $this->queryFactory->createInstanceByQueryString($query);
        $queryParts = $queryObject->getQueryParts();

        if ('selectQuery' == $this->rdfHelpers->getQueryType($query)) {
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
                // extend triple pattern for S, P and O, if a prefixed URL was used
                // we already know, that p and o are URIs!
                if ($this->commonNamespaces->isShortenedUri($triplePattern[1]['p'])) {
                    $extendedTriplePatternPUri = $this->commonNamespaces->extendUri($triplePattern[1]['p']);
                } else {
                    // set null because there is nothing to extend here
                    $extendedTriplePatternPUri = null;
                }
                if ($this->commonNamespaces->isShortenedUri($triplePattern[1]['o'])) {
                    $extendedTriplePatternOUri = $this->commonNamespaces->extendUri($triplePattern[1]['o']);
                } else {
                    // set null because there is nothing to extend here
                    $extendedTriplePatternOUri = null;
                }
                // ignore first pattern because it casts variables on s, p and o
                // 1. check, which s has wanted p and o
                foreach ($this->statementsPerGraph as $graph => $statements) {
                    foreach ($statements as $statement) {
                        $s = $statement->getSubject();
                        $p = $statement->getPredicate();
                        $o = $statement->getObject();
                        // standard check
                        if ($p->getUri() == $triplePattern[1]['p']
                            && $o->isNamed() && $o->getUri() == $triplePattern[1]['o']) {
                            $relevantS[$s->isNamed() ? $s->getUri() : $s->getBlankId()] = $s;
                        // also check, if extended versions of tripple pattern p and o lead to something
                        } elseif (null !== $extendedTriplePatternPUri || null !== $extendedTriplePatternOUri) {
                            // does predicate matches?
                            $predicateMatches = false;
                            if ($p->getUri() == $extendedTriplePatternPUri || $p->getUri() == $triplePattern[1]['p']) {
                                $predicateMatches = true;
                            }
                            // does object matches?
                            $objectMatches = false;
                            if ($o->isNamed() &&
                                ($o->getUri() == $extendedTriplePatternOUri || $o->getUri() == $triplePattern[1]['o'])) {
                                $objectMatches = true;
                            }
                            // if both matches, add $s
                            if ($predicateMatches && $objectMatches) {
                                $relevantS[$s->getUri()] = $s;
                            }
                        }
                    }
                }

                // 2. get all p and o for collected s
                foreach ($statements as $statement) {
                    $s = $statement->getSubject();
                    $p = $statement->getPredicate();
                    $o = $statement->getObject();

                    // if statement object is shortened, extend it before put to result
                    if ($statement->getObject()->isNamed()
                         && $this->commonNamespaces->isShortenedUri($statement->getObject())) {
                        $o = $this->nodeFactory->createNamedNode(
                            $this->commonNamespaces->extendUri($statement->getObject()->getUri())
                        );
                    }

                    if ($s->isNamed() && isset($relevantS[$s->getUri()])
                        || $s->isBlank() && isset($relevantS[$s->getBlankId()])) {
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
                foreach ($this->statementsPerGraph as $graph => $statements) {
                    foreach ($statements as $stmt) {
                        // only add new statements to result list
                        if (false === isset($addedStatementHashs[json_encode($stmt)])) {
                            // get value of predicate and object
                            $sValue = $this->getNodeValue($stmt->getSubject());
                            $pValue = $this->getNodeValue($stmt->getPredicate());
                            $oValue = $this->getNodeValue($stmt->getObject());

                            // store subject-predicate-object triple
                            $setEntries[$sValue . $pValue . $oValue] = array(
                                $triplePattern[0]['s'] => $stmt->getSubject(),
                                $triplePattern[0]['p'] => $stmt->getPredicate(),
                                $triplePattern[0]['o'] => $stmt->getObject()
                            );
                        }
                    }
                }
            // handle foo:s ?p ?o
            } elseif (1 == count($triplePattern)
                && false === strpos($triplePattern[0]['s'], 'http://')
                && 'uri' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statementsPerGraph as $graph => $statements) {
                    foreach ($statements as $stmt) {
                        if ($stmt->getSubject()->isNamed()) {
                            $fullUri = $this->commonNamespaces->extendUri($triplePattern[0]['s']);
                            // check for subject with full URI
                            // and check for subject with prefixed URI
                            if ($stmt->getSubject()->getUri() == $triplePattern[0]['s']
                                || $stmt->getSubject()->getUri() == $fullUri) {
                                // get value of predicate and object
                                $pValue = $this->getNodeValue($stmt->getPredicate());
                                $oValue = $this->getNodeValue($stmt->getObject());

                                // store predicate-object-tuple
                                $setEntries[$pValue . $oValue] = array(
                                    $triplePattern[0][$triplePattern[0]['p']] => $stmt->getPredicate(),
                                    $triplePattern[0][$triplePattern[0]['o']] => $stmt->getObject()
                                );
                            }
                        }
                    }
                }
            // handle <http://> ?p ?o
            } elseif (1 == count($triplePattern)
                && 'uri' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statementsPerGraph as $graph => $statements) {
                    foreach ($statements as $stmt) {
                        if ($stmt->getSubject()->isNamed()) {
                            $sUri = $stmt->getSubject()->getUri();
                            // if subject matches directly
                            $condition1 = $sUri == $triplePattern[0]['s'];
                            // if subject is shortened but its extended version matches
                            $condition2 = $this->commonNamespaces->isShortenedUri($sUri)
                                          && $this->commonNamespaces->extendUri($sUri) == $triplePattern[0]['s'];

                            if ($condition1 || $condition2) {
                                // get value of predicate and object
                                $pValue = $this->getNodeValue($stmt->getPredicate());
                                $oValue = $this->getNodeValue($stmt->getObject());

                                // add tuple
                                $setEntries[$pValue . $oValue] = array(
                                    $triplePattern[0][$triplePattern[0]['p']] => $stmt->getPredicate(),
                                    $triplePattern[0][$triplePattern[0]['o']] => $stmt->getObject()
                                );
                            }
                         }
                    }
                }

            // handle ?s <http://...#type> <http://...Person>
            } elseif (1 == count($triplePattern)
                && 'var' == $triplePattern[0]['s_type']
                && 'uri' == $triplePattern[0]['p_type']
                && 'uri' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statementsPerGraph as $graph => $statements) {
                    foreach ($statements as $stmt) {
                        // assuming predicate is named too
                        if ($stmt->getObject()->isNamed()) {
                            // predicate condition
                            $condition1 = $stmt->getPredicate()->getUri() == $triplePattern[0]['p'];

                            // object conditions
                            $oUri = $triplePattern[0]['o'];
                            $condition2 = $stmt->getObject()->getUri() == $oUri
                                || $this->commonNamespaces->extendUri($oUri) == $stmt->getObject()->getUri();

                            if ($condition1 && $condition2) {
                                $sValue = $this->getNodeValue($stmt->getSubject());
                                $setEntries[$sValue] = array(
                                    $triplePattern[0][$triplePattern[0]['s']] => $stmt->getSubject(),
                                );
                            }
                        }
                    }
                }

            // handle _:blankid ?p ?o
            } elseif (1 == count($triplePattern)
                && 'blanknode' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statementsPerGraph as $graph => $statements) {
                    foreach ($statements as $stmt) {
                        $subject = $stmt->getSubject();
                        if ($subject->isBlank()) {
                            if ('_:' !== substr($subject->getBlankId(), 0, 2)) {
                                $blankId = '_:'.$subject->getBlankId();
                            } else {
                                $blankId = $subject->getBlankId();
                            }
                            if (strtolower($blankId) == strtolower($triplePattern[0]['s'])) {
                                $setEntries[] = array(
                                    $triplePattern[0][$triplePattern[0]['p']] => $stmt->getPredicate(),
                                    $triplePattern[0][$triplePattern[0]['o']] => $stmt->getObject()
                                );
                            }
                        }
                    }
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
                    $relatedNodeUri = $relatedNode->getUri();
                    // we assume that the node is a named node
                    if (false == isset($filterInformation['possible_values'][$relatedNodeUri])) {
                        // if its node does not match with the filter requirements,
                        // remove the statement from the result
                        unset($setEntries[$key]);
                    }
                }
            }

            $result = new SetResultImpl($setEntries);
            $result->setVariables($queryParts['variables']);
            return $result;
        }

        throw new \Exception('Only select queries are supported for now.');
    }
}

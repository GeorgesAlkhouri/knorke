<?php

namespace Knorke;

use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIteratorFactory;
use Saft\Sparql\Query\QueryFactory;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;
use Saft\Store\Store;

class InMemoryStore implements Store
{
    /**
     * @var CommonNamespaces
     */
    protected $commonNamespaces;

    /**
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var NodeUtils
     */
    protected $nodeUtils;

    /**
     * @var QueryFactory
     */
    protected $queryFactory;

    /**
     * @var string
     */
    protected $defaultGraphUri = 'http://knorke/defaultGraph/';

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
        StatementIteratorFactory $statementIteratorFactory,
        CommonNamespaces $commonNamespaces
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
        $this->queryFactory = $queryFactory;
        $this->statementFactory = $statementFactory;
        $this->statementIteratorFactory = $statementIteratorFactory;
        $this->queryUtils = new QueryUtils();
    }

    /**
     * Adds multiple Statements to (default-) graph. It holds added statements as long as this instance exists.
     *
     * @param StatementIterator|array $statements StatementList instance must contain Statement instances which are
     *                                            'concret-' and not 'pattern'-statements.
     * @param Node $graph Overrides target graph. If set, all statements will be add to that graph, if available.
     * @param array $options It contains key-value pairs and should provide additional introductions for the store and/or
     *                       its adapter(s).
     */
    public function addStatements($statements, Node $graph = null, array $options = array())
    {
        $checkedStatements = array();

        // extend URIs if neccessary
        foreach ($statements as $statement) {
            $subject = $statement->getSubject();
            $predicate = $statement->getPredicate();
            $object = $statement->getObject();

            // check if its a prefixed one
            if ($subject->isNamed() && $this->commonNamespaces->isShortenedUri($subject->getUri())) {
                $subject = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($subject->getUri()));
            }

            // check if its a prefixed one
            if ($predicate->isNamed() && $this->commonNamespaces->isShortenedUri($predicate->getUri())) {
                $predicate = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($predicate->getUri()));
            }

            // check if its a prefixed one
            if ($object->isNamed() && $this->commonNamespaces->isShortenedUri($object->getUri())) {
                $object = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($object->getUri()));
            }

            $checkedStatements[] = $this->statementFactory->createStatement(
                $subject,
                $predicate,
                $object,
                $statement->getGraph()
            );
        }

        foreach ($checkedStatements as $statement) {
            if (null !== $graph) {
                $graphUri = $graph->getUri();
            // no graph information given, use default graph
            } elseif (null === $graph && null === $statement->getGraph()) {
                $graphUri = $this->defaultGraphUri;
            // no graph given, use graph information from $statement
            } elseif (null === $graph && $statement->getGraph()->isNamed()) {
                $graphUri = $statement->getGraph()->getUri();
            // no graph given, use graph information from $statement
            } elseif (null === $graph && false == $statement->getGraph()->isNamed()) {
                $graphUri = $this->defaultGraphUri;
            }
            // use hash to differenciate between statements (no doublings allowed)
            $statementHash = hash('sha256', serialize($statement));
            // add it
            $this->statements[$graphUri][$statementHash] = $statement;
        }
    }

    /**
     * Create a new graph with the URI given as Node. If the underlying store implementation doesn't support empty
     * graphs this method will have no effect.
     *
     * @param  NamedNode $graph            Instance of NamedNode containing the URI of the graph to create.
     * @param  array     $options optional It contains key-value pairs and should provide additional introductions
     *                                     for the store and/or its adapter(s).
     * @throws \Exception If given $graph is not a NamedNode.
     * @throws \Exception If the given graph could not be created.
     */
    public function createGraph(NamedNode $graph, array $options = array())
    {
        $this->statements[$graph->getUri()] = array();
    }

    /**
     * Removes all statements from a (default-) graph which match with given statement.
     *
     * @param  Statement $statement          It can be either a concrete or pattern-statement.
     * @param  Node      $graph     optional Overrides target graph. If set, all statements will be
     *                                       delete in that graph.
     * @param  array     $options   optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     */
    public function deleteMatchingStatements(
        Statement $statement,
        Node $graph = null,
        array $options = array()
    ) {
        if (null !== $graph) {
            $graphUri = $graph->getUri();

        // no graph information given, use default graph
        } elseif (null === $graph && null === $statement->getGraph()) {
            $graphUri = $this->defaultGraphUri;

        // no graph given, use graph information from $statement
        } elseif (null === $graph && $statement->getGraph()->isNamed()) {
            $graphUri = $statement->getGraph()->getUri();

        // no graph given, use graph information from $statement
        } elseif (null === $graph && false == $statement->getGraph()->isNamed()) {
            $graphUri = $this->defaultGraphUri;
        }

        // use hash to differenciate between statements (no doublings allowed)
        $statementHash = hash('sha256', json_encode($statement));

        // delete it
        unset($this->statements[$graphUri][$statementHash]);
    }

    /**
     * Removes the given graph from the store.
     *
     * @param  NamedNode $graph            Instance of NamedNode containing the URI of the graph to drop.
     * @param  array     $options optional It contains key-value pairs and should provide additional introductions
     *                                     for the store and/or its adapter(s).
     * @throws \Exception If given $graph is not a NamedNode.
     * @throws \Exception If the given graph could not be droped
     */
    public function dropGraph(NamedNode $graph, array $options = array())
    {
        unset($this->statements[$graph->getUri()]);
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
     * Has no function and returns an empty array.
     *
     * @return array Empty array
     */
    public function getGraphs()
    {
        $graphs = array();
        foreach (array_keys($this->statements) as $graphUri) {
            if ($this->defaultGraphUri == $graphUri) {
                $graphs[$graphUri] = $this->nodeFactory->createNamedNode($graphUri);
            }
        }
        return $graphs;
    }

    /**
     * It basically returns all stored statements.
     *
     * @param  Statement $statement          It can be either a concrete or pattern-statement.
     * @param  Node      $graph     optional Overrides target graph. If set, you will get all
     *                                       matching statements of that graph.
     * @param  array     $options   optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     * @return SetResult It contains Statement instances of all matching statements of the given graph.
     */
    public function getMatchingStatements(Statement $statement, Node $graph = null, array $options = array())
    {
        if (null !== $graph) {
            $graphUri = $graph->getUri();

        // no graph information given, use default graph
        } elseif (null === $graph && null === $statement->getGraph()) {
            $graphUri = $this->defaultGraphUri;

        // no graph given, use graph information from $statement
        } elseif (null === $graph && $statement->getGraph()->isNamed()) {
            $graphUri = $statement->getGraph()->getUri();

        // no graph given, use graph information from $statement
        } elseif (null === $graph && false == $statement->getGraph()->isNamed()) {
            $graphUri = $this->defaultGraphUri;
        }

        if (false == isset($this->statements[$graphUri])) {
            $this->statements[$graphUri] = array();
        }

        // if not default graph was requested
        if ($this->defaultGraphUri != $graphUri) {
            return new StatementSetResultImpl($this->statements[$graphUri]);

        // if default graph was requested, return matching statements from all graphs
        } else {
            $_statements = array();
            foreach ($this->statements as $graphUri => $statements) {
                foreach ($statements as $statement) {
                    if ($this->defaultGraphUri == $graphUri) {
                        $graph = null;
                    } else {
                        $graph = $this->nodeFactory->createNamedNode($graphUri);
                    }
                    $_statements[] = $this->statementFactory->createStatement(
                        $statement->getSubject(),
                        $statement->getPredicate(),
                        $statement->getObject(),
                        $graph
                    );
                }
            }
            return new StatementSetResultImpl($_statements);
        }
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
     * Returns true or false depending on whether or not the statements pattern has any matches in the given graph.
     *
     * @param  Statement $Statement It can be either a concrete or pattern-statement.
     * @param  Node $graph optional Overrides target graph.
     * @param  array $options optional It contains key-value pairs and should provide additional
     *                                       introductions for the store and/or its adapter(s).
     * @return boolean Returns true if at least one match was found, false otherwise.
     */
    public function hasMatchingStatement(Statement $statement, Node $graph = null, array $options = array())
    {
        if (null !== $graph) {
            $graphUri = $graph->getUri();
        // no graph information given, use default graph
        } elseif (null === $graph && null === $statement->getGraph()) {
            $graphUri = $this->defaultGraphUri;
        // no graph given, use graph information from $statement
        } elseif (null === $graph && $statement->getGraph()->isNamed()) {
            $graphUri = $statement->getGraph()->getUri();
        // no graph given, use graph information from $statement
        } elseif (null === $graph && false == $statement->getGraph()->isNamed()) {
            $graphUri = $this->defaultGraphUri;
        }
        // exception if at least one statement is already stored, use according graphURI
        if (0 < count($this->statements)) {
            $graphs = array_keys($this->statements);
            $graphUri = array_shift($graphs);
        }
        // if statement consists if only concrete nodes, so no anypattern instances
        if ($statement->isConcrete()) {
            // use hash to differenciate between statements (no doublings allowed)
            $statementHash = hash('sha256', serialize($statement));
            return isset($this->statements[$graphUri][$statementHash]);
        } else {
            // if at least one any pattern instance is part of the list
            $sMatches = false;
            $pMatches = false;
            $oMatches = false;
            $relevantStatements = $_relevantStatements = array();
            // check if there is one statement which has the given subject
            if ($statement->getSubject()->isPattern()) {
                $sMatches = true;
                if (isset($this->statements[$graphUri])) {
                    $relevantStatements = $this->statements[$graphUri];
                }
            } else {
                foreach ($this->statements[$graphUri] as $storedStatement) {
                    if ($statement->getSubject()->equals($storedStatement->getSubject())) {
                        $sMatches = true;
                        $relevantStatements[] = $storedStatement;
                    }
                }
                if (false == $sMatches) {
                    return true;
                }
            }
            // check if there is one statement which has the given predicate
            if ($statement->getPredicate()->isPattern()) {
                $pMatches = true;
            } else {
                foreach ($relevantStatements as $statementWithMatchedSubject) {
                    if ($statement->getPredicate()->equals($statementWithMatchedSubject->getPredicate())) {
                        $pMatches = true;
                        $_relevantStatements[] = $statementWithMatchedSubject;
                    }
                }
                if (false == $pMatches) {
                    return false;
                }
                // now are in $relevantStatements all statements with matches subject and predicate
                $relevantStatements = $_relevantStatements;
            }
            // check if there is one statement which has the given object
            if ($statement->getObject()->isPattern()) {
                $oMatches = true;
            } else {
                foreach ($relevantStatements as $stmtWithMatchedSubjectAndPredicate) {
                    if ($statement->getObject()->equals($stmtWithMatchedSubjectAndPredicate->getObject())) {
                        $oMatches = true;
                        // we found at least one S P O, lets stop and return true
                        break;
                    }
                }
                if (false == $oMatches) {
                    return false;
                }
                $relevantStatements = $_relevantStatements;
            }
            return $sMatches && $pMatches && $oMatches;
        }
    }

    /**
     * This method sends a SPARQL query to the store.
     *
     * @param string $query The SPARQL query to send to the store.
     * @param array $options It contains key-value pairs and should provide additional introductions for the store
     *                       and/or its adapter(s) (optional).
     * @return Result Returns result of the query. Its type depends on the type of the query.
     * @throws \Exception If query is no string, is malformed or an execution error occured.
     */
    public function query($query, array $options = array())
    {
        $queryObject = $this->queryFactory->createInstanceByQueryString($query);
        $queryParts = $queryObject->getQueryParts();

        // get graph from query
        if (isset($queryParts['graphs']) && 0 < count($queryParts['graphs'])) {
            $graphUri = $queryParts['graphs'][0];
        } else {
            $graphUri = $this->defaultGraphUri;
        }

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
                foreach ($this->statements[$graphUri] as $statement) {
                    $s = $statement->getSubject();
                    $p = $statement->getPredicate();
                    $o = $statement->getObject();
                    // standard check
                    if ($p->getUri() == $triplePattern[1]['p']
                        && $o->isNamed() && $o->getUri() == $triplePattern[1]['o']) {
                        $relevantS[$s->getUri()] = $s;

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

                // 2. get all p and o for collected s
                foreach ($this->statements[$this->defaultGraphUri] as $statement) {
                    $s = $statement->getSubject();
                    $p = $statement->getPredicate();
                    $o = $statement->getObject();

                    if ($s->isNamed() && isset($relevantS[$s->getUri()])) {
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
                foreach ($this->statements[$graphUri] as $stmt) {
                    $setEntries[] = array(
                        $triplePattern[0]['s'] => $stmt->getSubject(),
                        $triplePattern[0]['p'] => $stmt->getPredicate(),
                        $triplePattern[0]['o'] => $stmt->getObject()
                    );
                }

            // handle foo:s ?p ?o
            } elseif (1 == count($triplePattern)
                && false === strpos($triplePattern[0]['s'], 'http://')
                && 'uri' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statements[$graphUri] as $stmt) {
                    if ($stmt->getSubject()->isNamed()) {
                        $fullUri = $this->commonNamespaces->extendUri($triplePattern[0]['s']);
                        // check for subject with full URI
                        // and check for subject with prefixed URI
                        if ($stmt->getSubject()->getUri() == $triplePattern[0]['s']
                            || $stmt->getSubject()->getUri() == $fullUri) {
                            $setEntries[] = array(
                                $triplePattern[0][$triplePattern[0]['p']] => $stmt->getPredicate(),
                                $triplePattern[0][$triplePattern[0]['o']] => $stmt->getObject()
                            );
                        }
                    }
                }

            // handle <http://> ?p ?o
            } elseif (1 == count($triplePattern)
                && 'uri' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statements[$graphUri] as $stmt) {
                    if ($stmt->getSubject()->isNamed()) {
                        $sUri = $stmt->getSubject()->getUri();
                        // if subject matches directly
                        $condition1 = $sUri == $triplePattern[0]['s'];

                        // if subject is shortened but its extended version matches
                        $condition2 = $this->commonNamespaces->isShortenedUri($sUri)
                            && $this->commonNamespaces->extendUri($sUri) == $triplePattern[0]['s'];

                        if ($condition1 || $condition2) {
                            $setEntries[] = array(
                                $triplePattern[0][$triplePattern[0]['p']] => $stmt->getPredicate(),
                                $triplePattern[0][$triplePattern[0]['o']] => $stmt->getObject()
                            );
                        }
                    }
                }

            // handle _:blankid ?p ?o
            } elseif (1 == count($triplePattern)
                && 'blanknode' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {
                // generate result
                foreach ($this->statements[$graphUri] as $stmt) {
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

<?php

namespace Knorke;

use Saft\Store\BasicTriplePatternStore;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIteratorFactory;
use Saft\Sparql\Query\QueryFactory;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\SetResultImpl;

class InMemoryStore extends BasicTriplePatternStore
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
        parent::__construct($nodeFactory, $statementFactory, $queryFactory, $statementIteratorFactory);

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

        parent::addStatements($checkedStatements, $graph, $options);
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
     * @param string $query The SPARQL query to send to the store.
     * @param array $options It contains key-value pairs and should provide additional introductions for the store
     *                       and/or its adapter(s) (optional).
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
                foreach ($this->statements['http://saft/defaultGraph/'] as $statement) {
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
                foreach ($this->statements['http://saft/defaultGraph/'] as $statement) {
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
                foreach ($this->statements['http://saft/defaultGraph/'] as $stmt) {
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
                foreach ($this->statements['http://saft/defaultGraph/'] as $stmt) {
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
                foreach ($this->statements['http://saft/defaultGraph/'] as $stmt) {
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
                foreach ($this->statements['http://saft/defaultGraph/'] as $stmt) {
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

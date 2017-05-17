<?php

namespace Knorke\Store;

use Knorke\Store\QueryHandler\BlankVarVar;
use Knorke\Store\QueryHandler\UriVarVar;
use Knorke\Store\QueryHandler\VarUriUri;
use Knorke\Store\QueryHandler\VarVarVar_VarUriUri;
use Knorke\Store\QueryHandler\VarVarVar_VarUriUri_VarUriLiteral;
use Knorke\Store\QueryHandler\VarVarVar;
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

            /*
             * handle ?s ?p ?o .
             *      ?s <http://...#type> <http://...Person> .
             *      ?s <http:// ..> "literal" .
             */
            if (3 == count($triplePattern)
                // ?s ?p ?o.
                && 'var' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']
                // ?s <http://...#type> <http://...Person> .
                && 'var' == $triplePattern[1]['s_type']
                && 'uri' == $triplePattern[1]['p_type']
                && 'uri' == $triplePattern[1]['o_type']
                // ?s <http:// ..> "literal" .
                && 'var' == $triplePattern[2]['s_type']
                && 'uri' == $triplePattern[2]['p_type']
                && 'typed-literal' == $triplePattern[2]['o_type']) {

                $queryHandler = new VarVarVar_VarUriUri_VarUriLiteral(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            /*
             * handle ?s ?p ?o
             *        ?s rdf:type foaf:Person
             */
            } elseif (2 == count($triplePattern)
                // ?s ?p ?o.
                && 'var' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']
                // ?s rdf:type foaf:Person.
                && 'var' == $triplePattern[1]['s_type']
                && 'uri' == $triplePattern[1]['p_type']
                && 'uri' == $triplePattern[1]['o_type']) {

                $queryHandler = new VarVarVar_VarUriUri(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            /*
             * handle foo:s ?p ?o
             */
            } elseif (1 == count($triplePattern)
                && false === strpos($triplePattern[0]['s'], 'http://')
                && 'uri' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {

                $queryHandler = new UriVarVar(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            /*
             * handle <http://> ?p ?o
             */
            } elseif (1 == count($triplePattern)
                && 'uri' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {

                $queryHandler = new UriVarVar(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            /*
             * handle ?s <http://...#type> <http://...Person>
             */
            } elseif (1 == count($triplePattern)
                && 'var' == $triplePattern[0]['s_type']
                && 'uri' == $triplePattern[0]['p_type']
                && 'uri' == $triplePattern[0]['o_type']) {

                $queryHandler = new VarUriUri(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            /*
             * handle _:blankid ?p ?o
             */
            } elseif (1 == count($triplePattern)
                && 'blanknode' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {

                $queryHandler = new BlankVarVar(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            /*
             * handle ?s ?p ?o
             */
            } elseif ((1 == count($triplePattern) || 2 == count($triplePattern))
                && 'var' == $triplePattern[0]['s_type']
                && 'var' == $triplePattern[0]['p_type']
                && 'var' == $triplePattern[0]['o_type']) {

                $queryHandler = new VarVarVar(
                    $this->commonNamespaces,
                    $this->nodeFactory
                );
                $result = $queryHandler->handle(
                    $this->statementsPerGraph,
                    $triplePattern,
                    $this->getFiltersIfAvailable($queryParts)
                );

            } else {
                throw new \Exception('Unknown query type given.');
            }

            $result->setVariables($queryParts['variables']);
            return $result;
        }

        throw new \Exception('Only select queries are supported for now.');
    }
}

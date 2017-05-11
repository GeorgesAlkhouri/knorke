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

class InMemoryStore extends AbstractStatementStore
{
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
        // adapt blank node ids to be fully random, so that they dont collide with already stored
        // blank nodes are temporary and are only valid during a transaction, like adding statements.
        $blankNodeRegistry = array();
        $checkedStatements = array();

        // extend URIs if neccessary
        foreach ($statements as $statement) {
            $subject = $statement->getSubject();
            $predicate = $statement->getPredicate();
            $object = $statement->getObject();

            /*
             * subject
             */
            // check if its a prefixed one
            if ($subject->isNamed() && $this->commonNamespaces->isShortenedUri($subject->getUri())) {
                $subject = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($subject->getUri()));

            // blank node ID is new
            } elseif ($subject->isBlank() && false == isset($blankNodeRegistry[$subject->getBlankId()])) {
                $sHash = $this->generateBlankIdHash();
                $blankNodeRegistry[$subject->getBlankId()] = $sHash;

                // create new blank node with better ID
                $subject = $this->nodeFactory->createBlankNode($sHash);

            // blank node ID is known
            } elseif ($subject->isBlank() && true == isset($blankNodeRegistry[$subject->getBlankId()])) {
                $subject = $this->nodeFactory->createBlankNode($blankNodeRegistry[$subject->getBlankId()]);
            }

            /*
             * predicate
             */
            // check if its a prefixed one
            if ($predicate->isNamed() && $this->commonNamespaces->isShortenedUri($predicate->getUri())) {
                $predicate = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($predicate->getUri()));
            }

            /*
             * object
             */
            // check if its a prefixed one
            if ($object->isNamed() && $this->commonNamespaces->isShortenedUri($object->getUri())) {
                $object = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($object->getUri()));

            // blank node ID is new
            } elseif ($object->isBlank() && false == isset($blankNodeRegistry[$object->getBlankId()])) {
                $oHash = $this->generateBlankIdHash();
                $blankNodeRegistry[$object->getBlankId()] = $oHash;

                // create new blank node with better ID
                $object = $this->nodeFactory->createBlankNode($oHash);

            // blank node ID is known
            } elseif ($object->isBlank() && true == isset($blankNodeRegistry[$object->getBlankId()])) {
                $object = $this->nodeFactory->createBlankNode($blankNodeRegistry[$object->getBlankId()]);
            }

            $checkedStatements[] = $this->statementFactory->createStatement(
                $subject,
                $predicate,
                $object,
                $statement->getGraph()
            );
        }

        foreach ($checkedStatements as $statement) {
            $graphUri = $this->retrieveGraphUri($graph, $statement);

            // use hash to differenciate between statements (no doublings allowed)
            $statementHash = hash('sha256', serialize($statement));
            // add it
            $this->statementsPerGraph[$graphUri][$statementHash] = $statement;
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
        $this->statementsPerGraph[$graph->getUri()] = array();
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
        unset($this->statementsPerGraph[$graphUri][$statementHash]);
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
        unset($this->statementsPerGraph[$graph->getUri()]);
    }

    /**
     * Has no function and returns an empty array.
     *
     * @return array Empty array
     */
    public function getGraphs()
    {
        $graphs = array();
        foreach (array_keys($this->statementsPerGraph) as $graphUri) {
            $graphs[$graphUri] = $this->nodeFactory->createNamedNode($graphUri);
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

        if (false == isset($this->statementsPerGraph[$graphUri])) {
            $this->statementsPerGraph[$graphUri] = array();
        }

        // if not default graph was requested
        if ($this->defaultGraphUri != $graphUri) {
            return new StatementSetResultImpl($this->statementsPerGraph[$graphUri]);

        // if default graph was requested, return matching statements from all graphs
        } else {
            $_statements = array();
            foreach ($this->statementsPerGraph as $graphUri => $statements) {
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
        if (0 < count($this->statementsPerGraph)) {
            $graphs = array_keys($this->statementsPerGraph);
            $graphUri = array_shift($graphs);
        }
        // if statement consists if only concrete nodes, so no anypattern instances
        if ($statement->isConcrete()) {
            // use hash to differenciate between statements (no doublings allowed)
            $statementHash = hash('sha256', serialize($statement));
            return isset($this->statementsPerGraph[$graphUri][$statementHash]);
        } else {
            // if at least one any pattern instance is part of the list
            $sMatches = false;
            $pMatches = false;
            $oMatches = false;
            $relevantStatements = $_relevantStatements = array();
            // check if there is one statement which has the given subject
            if ($statement->getSubject()->isPattern()) {
                $sMatches = true;
                if (isset($this->statementsPerGraph[$graphUri])) {
                    $relevantStatements = $this->statementsPerGraph[$graphUri];
                }
            } else {
                foreach ($this->statementsPerGraph[$graphUri] as $storedStatement) {
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
     * @param NamedNode $graph optional
     * @param Statement $statement optional
     * @return string|null
     */
    protected function retrieveGraphUri(NamedNode $graph = null, Statement $statement = null)
    {
        if (null !== $graph) {
            return $graph->getUri();

        } elseif (null === $graph && null == $statement) {
            return $this->defaultGraphUri;

        // either graph nor statement given, therefore use default graph
        } elseif (null === $graph && null === $statement->getGraph()) {
            return $this->defaultGraphUri;

        // no graph given, use graph information from $statement
        } elseif (null === $graph && $statement->getGraph()->isNamed()) {
            return $statement->getGraph()->getUri();
        }

        return null;
    }
}

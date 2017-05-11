<?php

namespace Knorke\Store;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\Factory;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\Node;
use Saft\Rdf\NamedNode;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\Statement;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\StatementIterator;
use Saft\Rdf\StatementIteratorFactory;
use Saft\Sparql\Query\QueryFactory;
use Saft\Sparql\Result\SetResultImpl;
use Saft\Store\Store;

class SemanticDbl extends AbstractStatementStore
{
    protected $tables;

    /**
     * @param string $graphUri
     * @param int $s
     * @param int $p
     * @param int $o
     */
    protected function addQuad(string $graphUri, int $s, int $p, int $o) : bool
    {
        // is quad already available?
        $quad = $this->pdo->row(
            'SELECT * FROM quad WHERE graph = ?
                AND subject_id = ?
                AND predicate_id = ?
                AND object_id = ?',
            $graphUri, $s, $p, $o
        );

        // create quad if not available
        if (null === $quad) {
            $this->pdo->insert('quad', array(
                'graph' => $graphUri,
                'subject_id' => $s,
                'predicate_id' => $p,
                'object_id' => $o,
            ));
            return true;
        }

        return false;
    }

    /**
     * @param Statement $statement
     * @param NamedNode $graph Optional, default: null
     * @todo care about language and datatype
     */
    protected function addStatement(Statement $statement, NamedNode $graph = null)
    {
        /*
         * subject
         */
        if ($statement->getSubject()->isNamed()) {
            $sUri = $statement->getSubject()->getUri();
            // short to full URI
            if ($this->commonNamespaces->isShortenedUri($sUri)) {
                $subjectUID = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($sUri));
            } else {
                $subjectUID = $sUri;
            }
            $subjectType = 'uri';
        } else {
            $subjectUID = $statement->getSubject()->getBlankId();
            $subjectType = 'blanknode';
        }

        // is subject there?
        $subjectRow = $this->pdo->row(
            'SELECT id FROM value WHERE value = ? AND type = ?',
            $subjectUID, $subjectType
        );

        /*
         * predicate
         */
        $pUri = $statement->getPredicate()->getUri();
        // short to full URI
        if ($this->commonNamespaces->isShortenedUri($pUri)) {
            $predicateUID = $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($pUri));
        } else {
            $predicateUID = $pUri;
        }

        $predicateType = 'uri';

        // is predicate there?
        $predicateRow = $this->pdo->row(
            'SELECT id FROM value WHERE value = ? AND type = ?',
            $predicateUID, $predicateType
        );

        /*
         * object
         */
        $objectLanguage = null;
        $objectDatatype = null;
        if ($statement->getObject()->isNamed()) {
            $oUri = $statement->getObject()->getUri();
            // short to full URI
            if ($this->commonNamespaces->isShortenedUri($oUri)) {
                $objectUID = $this->commonNamespaces->extendUri($oUri);
            } else {
                $objectUID = $oUri;
            }
            $objectType = 'uri';
        } elseif ($statement->getObject()->isBlank()) {
            $objectUID = $statement->getObject()->getBlankId();
            $objectType = 'blanknode';
        } else {
            $objectUID = $statement->getObject()->getValue();
            $objectType = 'literal';
            $objectLanguage = $statement->getObject()->getLanguage();
            $objectDatatype = $statement->getObject()->getDatatype();
        }

        // is object there?
        $objectRow = $this->pdo->row(
            'SELECT id, value FROM value WHERE value = ? AND type = ?',
            $objectUID, $objectType
        );

        /*
         * graph
         */
        $graphUri = $this->retrieveGraphUri($graph, $statement);

        if (null == $this->pdo->row('SELECT uri FROM graph WHERE uri = ?', $graphUri)) {
            // if graph could be found and its the default one, create it
            if ($this->defaultGraphUri == $graphUri) {
                $this->createGraph($this->nodeFactory->createNamedNode($this->defaultGraphUri));
            } else {
                throw new \Exception('Target graph not available: '. $graphUri);
            }
        }

        /*
         * add entries, which are not available already
         */
        if (null === $subjectRow) {
            $subjectId = $this->addValue($subjectUID, $subjectType);
        } else {
            $subjectId = $subjectRow['id'];
        }

        if (null === $predicateRow) {
            $predicateId = $this->addValue($predicateUID, $predicateType);
        } else {
            $predicateId = $predicateRow['id'];
        }

        if (null === $objectRow) {
            $objectId = $this->addValue($objectUID, $objectType, $objectLanguage, $objectDatatype);
        } else {
            $objectId = $objectRow['id'];
        }

        return $this->addQuad($graphUri, $subjectId, $predicateId, $objectId);
    }

    /**
    * Adds multiple Statements to (default-) graph.
    *
    * @param StatementIterator|array $statements StatementList instance must contain Statement
    *                                            instances which are 'concret-' and not 'pattern'-statements.
    * @param Node $graph Overrides target graph. If set, all statements will be add to that graph,
    *                    if it is available. (optional)
    * @param array $options Key-value pairs which provide additional introductions for the store
    *                       and/or its adapter(s). (optional)
    */
    public function addStatements($statements, Node $graph = null, array $options = array())
    {
        // reset stored statementes
        $this->statementsPerGraph = array();

        // adapt blank node ids to be fully random, so that they dont collide with already stored
        // blank nodes are temporary and are only valid during a transaction, like adding statements.
        $blankNodeRegistry = array();
        $adaptedStatements = array();

        foreach ($statements as $stmt) {
            $s = $stmt->getSubject();
            $p = $stmt->getPredicate();
            $o = $stmt->getObject();
            $g = $stmt->getGraph();

            /*
             * subject
             */
            // blank node ID is new
            if ($s->isBlank() && false == isset($blankNodeRegistry[$s->getBlankId()])) {
                $sHash = $this->generateBlankIdHash();
                $blankNodeRegistry[$s->getBlankId()] = $sHash;

                // create new blank node with better ID
                $s = $this->nodeFactory->createBlankNode($sHash);

            // blank node ID is known
            } elseif ($s->isBlank() && true == isset($blankNodeRegistry[$s->getBlankId()])) {
                $s = $this->nodeFactory->createBlankNode($blankNodeRegistry[$s->getBlankId()]);
            }

            /*
             * object
             */
            // blank node ID is new
            if ($o->isBlank() && false == isset($blankNodeRegistry[$o->getBlankId()])) {
                $oHash = $this->generateBlankIdHash();
                $blankNodeRegistry[$o->getBlankId()] = $oHash;

                // create new blank node with better ID
                $o = $this->nodeFactory->createBlankNode($oHash);

            // blank node ID is known
            } elseif ($o->isBlank() && true == isset($blankNodeRegistry[$o->getBlankId()])) {
                $o = $this->nodeFactory->createBlankNode($blankNodeRegistry[$o->getBlankId()]);
            }

            $adaptedStatements[] = $this->statementFactory->createStatement($s, $p, $o, $g);
        }

        foreach ($adaptedStatements as $statement) {
            $this->addStatement($statement, $graph);
        }
    }

    /**
     * @param string $value
     * @param string $type
     * @param string $language
     * @param string $datatype
     * @return int ID of the new entry.
     */
    protected function addValue(
        string $value,
        string $type,
        string $language = null,
        string $datatype = null
    ) : int {
        $this->pdo->insert('value', array(
            'value' => $value,
            'type' => $type,
            'language' => $language,
            'datatype' => $datatype,
        ));

        return $this->pdo->getPdo()->lastInsertId();
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $database
     * @param string $host Optional, default is 127.0.0.1
     */
    public function connect(string $username, string $password, string $database, string $host = '127.0.0.1')
    {
        $this->pdo = Factory::create(
            'mysql:host='. $host .';'.
                'dbname='. $database .';'.
                'charset=utf8;',
            $username,
            $password
        );
    }

    /**
     * Create a new graph with the URI given as Node. If the underlying store implementation doesn't
     * support empty graphs this method will have no effect.
     *
     * @param NamedNode $graph   Instance of NamedNode containing the URI of the graph to create.
     * @param array     $options It contains key-value pairs and should provide additional introductions for
     *                           the store and/or its adapter(s).
     * @throws \Exception if given $graph is not a NamedNode.
     * @throws \Exception if the given graph could not be created.
     */
    public function createGraph(NamedNode $graph, array $options = array())
    {
        $row = $this->pdo->row("SELECT uri FROM graph WHERE uri = ?", $graph->getUri());

        if (null === $row) {
            $this->pdo->insert('graph', array('uri' => $graph->getUri()));
        }
    }

    /**
     * Removes all statements from a (default-) graph which match with given statement.
     *
     * @param Statement $statement It can be either a concrete or pattern-statement.
     * @param Node      $graph     Overrides target graph. If set, all statements will be delete
     *                             in that graph. (optional)
     * @param array     $options   Key-value pairs which provide additional introductions for
     *                             the store and/or its adapter(s). (optional)
     */
    public function deleteMatchingStatements(
        Statement $statement,
        Node $graph = null,
        array $options = array()
    ) {
        // reset stored statementes
        $this->statementsPerGraph = array();

        /*
         * check kind of statement:
         * - ?, ?, ?
         * - s, ?, ? (or p or o set)
         * - s, p, ? (or po or so set)
         * - s, p, o
         */
        $subject = $this->getNodeValue($statement->getSubject());
        $predicate = $this->getNodeValue($statement->getPredicate());
        $object = $this->getNodeValue($statement->getObject());
        $graph = $this->nodeFactory->createNamedNode($this->retrieveGraphUri($graph, $statement));
        $sOperator = $pOperator = $oOperator = '=';

        if ('ANY' == $subject) {
            $sOperator = 'LIKE';
            $subject = '%%';
        }
        if ('ANY' == $predicate) {
            $pOperator = 'LIKE';
            $predicate = '%%';
        }

        if ('ANY' == $object) {
            $oOperator = 'LIKE';
            $object = '%%';
        }

        // fetch value id for s, p and o for given values
        $values = $this->pdo->run(
            'SELECT subject_id, predicate_id, object_id
               FROM quad q
                    LEFT JOIN value v1 ON q.subject_id = v1.id
                    LEFT JOIN value v2 ON q.predicate_id = v2.id
                    LEFT JOIN value v3 ON q.object_id = v3.id
              WHERE v1.value '. $sOperator .' ?
                    AND v2.value '. $pOperator .' ?
                    AND v3.value '. $oOperator .' ?
                    AND q.graph = ?',
              $subject, $predicate, $object, $graph
        );

        $valueIdsInUse = array();

        // remove quads
        foreach ($values as $value) {
            $valueIdsInUse[$value['subject_id']] = $value['subject_id'];
            $valueIdsInUse[$value['predicate_id']] = $value['predicate_id'];
            $valueIdsInUse[$value['object_id']] = $value['object_id'];

            $this->pdo->delete('quad', array(
                'subject_id' => $value['subject_id'],
                'predicate_id' => $value['predicate_id'],
                'object_id' => $value['object_id'],
            ));
        }

        // remove values, if not in use anymore
        foreach ($valueIdsInUse as $valueId) {
            $result = $this->pdo->run(
                'SELECT graph
                   FROM quad
                  WHERE subject_id = ? OR predicate_id = ? OR object_id = ?',
                  $valueId, $valueId, $valueId
            );

            if (0 == count($result)) {
                $this->pdo->delete('value', array('id' => $valueId));
            }
        }
    }

    /**
     * Removes the given graph from the store as well as all related triples.
     *
     * @param NamedNode $graph   Instance of NamedNode containing the URI of the graph to drop.
     * @param array     $options It contains key-value pairs and should provide additional introductions for
     *                           the store and/or its adapter(s).
     * @throws \Exception if given $graph is not a NamedNode.
     * @throws \Exception if the given graph could not be droped
     */
    public function dropGraph(NamedNode $graph, array $options = array())
    {
        // delete stored statementes for graph to drop
        $this->statementsPerGraph[$graph->getUri()] = array();

        // remove quads related to the given graph
        $this->pdo->run('DELETE FROM quad WHERE graph = ?', $graph->getUri());

        /*
         * remove all values, if not in use anymore (because of removed quads)
         */
        // gather values which are not in use anymore
        $valuesToRemove = $this->pdo->run(
            'SELECT id
               FROM value
              WHERE id NOT IN (SELECT subject_id FROM quad)
                    AND id NOT IN (SELECT predicate_id FROM quad)
                    AND id NOT IN (SELECT object_id FROM quad)'
        );

        // remove all found values
        foreach ($valuesToRemove as $value) {
            $this->pdo->run('DELETE FROM value WHERE id = ?', $value['id']);
        }

        // remove graph itself
        $this->pdo->run('DELETE FROM graph WHERE uri = ?', $graph->getUri());
    }

    public function getDb() : EasyDB
    {
        return $this->pdo;
    }

    /**
     * Returns a list of all available graph URIs of the store. It can also respect access control,
     * to only returned available graphs in the current context. But that depends on the implementation
     * and can differ.
     *
     * @return array Array with the graph URI as key and a NamedNode as value for each graph.
     */
    public function getGraphs() : array
    {
        $graphs = array();

        $rows = $this->pdo->run('SELECT uri FROM graph');
        foreach ($rows as $entry) {
            $graphs[] = $this->nodeFactory->createNamedNode($entry['uri']);
        }

        return $graphs;
    }

    /**
     * Loads quads from a graph and transforms them to a statement list.
     *
     * @param NamedNode $graph
     * @return array
     */
    public function getStatementsFromGraph(NamedNode $graph) : StatementIterator
    {
        $quads = $this->pdo->run(
            'SELECT v1.value as subject,
                    v2.value as predicate,
                    v3.value as object,
                    v3.type as objecttype
               FROM quad q
                    LEFT JOIN value v1 ON q.subject_id = v1.id
                    LEFT JOIN value v2 ON q.predicate_id = v2.id
                    LEFT JOIN value v3 ON q.object_id = v3.id
              WHERE q.graph = ?',
            $graph->getUri()
        );

        $statements = array();

        foreach ($quads as $quad) {
            // subject
            if (true === $this->rdfHelpers->simpleCheckURI($quad['subject'])) {
                $s = $this->nodeFactory->createNamedNode($quad['subject']);
            } else { // blanknode
                $s = $this->nodeFactory->createBlankNode($quad['subject']);
            }

            // predicate
            $p = $this->nodeFactory->createNamedNode($quad['predicate']);

            // object
            switch ($quad['objecttype']) {
                case 'uri':
                    $o = $this->nodeFactory->createNamedNode($quad['object']);
                    break;
                case 'blanknode':
                    $o = $this->nodeFactory->createBlankNode($quad['object']);
                    break;
                case 'literal':
                    $o = $this->nodeFactory->createLiteral($quad['object']);
                    break;

                default:
                    throw new KnorkeException('Unknown object type found: '. $quad['object-value']);
                    break;
            }

            $statements[] = $this->statementFactory->createStatement($s, $p, $o, $graph);
        }

        return $this->statementIteratorFactory->createStatementIteratorFromArray($statements);
    }

    /**
     * Get information about the store and its features.
     *
     * @return array Array which contains information about the store and its features.
     */
    public function getStoreDescription() : array
    {
        return array(
            'rdbms' => 'mysql',
            'support' => array(
                'sparql1.0' => 'limited',
                'sparql1.1' => 'limited'
            )
        );
    }

    /**
     * Returns true or false depending on whether or not the statements pattern
     * has any matches in the given graph.
     *
     * @param Statement $statement It can be either a concrete or pattern-statement.
     * @param Node      $graph     Overrides target graph. (optional)
     * @param array     $options   It contains key-value pairs and should provide additional
     *                             introductions for the store and/or its adapter(s). (optional)
     * @return boolean Returns true if at least one match was found, false otherwise.
     * @throws \Exception because its not implemented yet
     */
    public function hasMatchingStatement(
        Statement $statement,
        Node $graph = null,
        array $options = array()
    ) : bool {
        throw new \Exception('Not implemented yet. Use query function.');
    }

    /**
     * @return bool True if all tables exists, false otherwise.
     */
    public function isSetup()
    {
        return true === $this->tableExists('graph')
            && true === $this->tableExists('quad')
            && true === $this->tableExists('value');
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

        if (isset($queryParts['graphs'])) {
            $graphs = $queryParts['graphs'];
        } else {
            $graphs = array($this->defaultGraphUri);
        }

        // get statements from graph, if not already done
        foreach ($graphs as $graph) {
            if (false === isset($this->statementsPerGraph[$graph])) {
                $this->statementsPerGraph[$graph] = $this->getStatementsFromGraph(
                    $this->nodeFactory->createNamedNode($graph)
                );
            }
        }

        return parent::query($query, $options);
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

    /**
     * Setup database tables, if not they are not there yet.
     */
    public function setup()
    {
        if ($this->isSetup()) {
            return;
        }

        if (false === $this->tableExists('graph')) {
            $this->pdo->run(
                "CREATE TABLE `graph` (
                  `uri` varchar(255) COLLATE utf8_unicode_ci NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
            );
            $this->pdo->run("ALTER TABLE `graph` ADD PRIMARY KEY (`uri`);");
        }

        if (false === $this->tableExists('quad')) {
            $this->pdo->run(
                "CREATE TABLE `quad` (
                  `graph` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `subject_id` int(12) NOT NULL,
                  `predicate_id` int(12) NOT NULL,
                  `object_id` int(12) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT;"
            );
            $this->pdo->run(
                "ALTER TABLE `quad` ADD PRIMARY KEY (`graph`,`subject_id`,`predicate_id`,`object_id`);"
            );
        }

        if (false === $this->tableExists('value')) {
            $this->pdo->run(
                "CREATE TABLE `value` (
                  `id` int(12) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `value` text COLLATE utf8_unicode_ci NOT NULL,
                  `type` enum('uri','blanknode','literal') COLLATE utf8_unicode_ci NOT NULL,
                  `language` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `datatype` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
            );
            $this->pdo->run(
                "ALTER TABLE `value`
                  ADD KEY `type` (`type`),
                  ADD KEY `language` (`language`),
                  ADD KEY `datatype` (`datatype`);"
            );
            $this->pdo->run("ALTER TABLE `value` ADD FULLTEXT KEY `value` (`value`);");
        }
    }

    /**
     * Checks if a SQL table exists.
     *
     * @param string $table
     * @return boolean
     */
    protected function tableExists($table)
    {
        foreach ($this->pdo->run('SHOW TABLES') as $value) {
            if (array_values($value)[0] == $table) {
                return true;
            }
        }

        return false;
    }
}

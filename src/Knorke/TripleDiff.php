<?php

namespace Knorke;

use Saft\Rdf\RdfHelpers;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\StatementFactory;
use Saft\Rdf\Statement;
use Saft\Store\Store;

/**
 * This class provides functions to create a diff between 2 sets of statements (triple). It basically
 * shows which statements are unique to which set.
 */
class TripleDiff
{
    protected $commonNamespaces;
    protected $nodeFactory;
    protected $rdfHelpers;
    protected $statementFactory;
    protected $store;

    /**
     * @param RdfHelpers $rdfHelpers
     * @param CommonNamespaces $commonNamespaces
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     * @param Store $store
     */
    public function __construct(
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces,
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        Store $store
    ) {
        $this->rdfHelpers = $rdfHelpers;
        $this->commonNamespaces = $commonNamespaces;
        $this->nodeFactory = $nodeFactory;
        $this->statementFactory = $statementFactory;
        $this->store = $store;
    }

    /**
     * Computes the diff of two graphs of a store.
     *
     * @param string $graphUri1 URI of the first graph.
     * @param string $graphUri2 URI of the second graph to check against.
     * @return array Array of 2 elements: first one contains all statements which are unique to
     *               the first graph, second one contains all statements which are unique to the
     *               second graph.
     */
    public function computeDiffForTwoGraphs(string $graphUri1, string $graphUri2) : array
    {
        // query function to get all statements of the graph
        $query = function($graphUri)
        {
          $result = $this->store->query('
              SELECT * FROM <' . $graphUri . '> WHERE {
                  ?s ?p ?o.
              }
          ');
          return $result;
        };

        $graphResult1 = $query($graphUri1);
        $graphResult2 = $query($graphUri2);

        // function which maps the query result to a statement
        $resultsToStatements = function($resultArray)
        {
          return $this->statementFactory->createStatement(
              $resultArray['s'],
              $resultArray['p'],
              $resultArray['o']);
        };

        $statementSet1 = array_map($resultsToStatements, iterator_to_array($graphResult1));
        $statementSet2 = array_map($resultsToStatements, iterator_to_array($graphResult2));

        return $this->computeDiffForTwoTripleSets($statementSet1, $statementSet2);
    }

    /**
     * Computes the diff of two sets containing triples or quads (HashableStatement instances).
     *
     * @param array $statementSet1 Set of statements. Must be of type HashableStatement.
     * @param array $statementSet2 Set of statements. Must be of type HashableStatement.
     * @param bool $considerGraphUri If comparing quads, graph URIs will be also considered.
     * @return array Array of 2 elements: first one contains all statements which are unique to
     *               the first set, second one contains all statements which are unique to the
     *               second set.
     */
    public function computeDiffForTwoTripleSets(array $statementSet1, array $statementSet2, $considerGraphUri = false) : array
    {

        // TODO check for blank nodes and abort if necessary

        $reduceToHash = function($carry , $item) use ($considerGraphUri)
        {
            $hash = $this->hash($item, $considerGraphUri);
            $carry[$hash] = $item;

            return $carry;
        };

        // Nice benefit: hash set does not contain duplicates
        $hashset1 = array_reduce($statementSet1, $reduceToHash, []);
        $hashset2 = array_reduce($statementSet2, $reduceToHash, []);

        //$inBoth = array_intersect_key($hashset1, $hashset2);
        $diffSet1 = array_diff_key($hashset1, $hashset2);
        $diffSet2 = array_diff_key($hashset2, $hashset1);

        return array(
            //array_values($inBoth),
            array_values($diffSet1),
            array_values($diffSet2)
        );
    }

    /**
     * Computes a hash from a triple or quad by combining s p o [g].
     * @param Statement $statement The RDF statement which can be a triple or quad.
     * @param boolean $considerGraphUri Boolean, whether the graph URI should be considered for hash generation or not.
     * @param $algorithm Name of the hash algorithm.
     * @return hash
     */
      public function hash(Statement $statement, $considerGraphUri = false, $algorithm = 'sha256')
      {
          $combined = $statement->getSubject() . " " . $statement->getPredicate() . " " . $statement->getObject();
          if ($statement->isQuad() && $considerGraphUri)
          {
              $combined = $combined . " " . $statement->getGraph();
          }

          return hash($algorithm, $combined);
      }
}

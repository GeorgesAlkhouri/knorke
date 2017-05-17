<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  foo:bar ?p ?o .
 */
class UriVarVar extends AbstractQueryHandler
{
    /**
     * @param array $statementsPerGraph
     * @param array $triplePattern
     * @param array $filterInformation Default: null
     * @return Result
     */
    public function handle(
        array $statementsPerGraph,
        array $triplePattern,
        array $filterInformation = null
    ) : Result {
        $entries = array();

        foreach ($statementsPerGraph as $graph => $statements) {
            foreach ($statements as $stmt) {
                if ($stmt->getSubject()->isNamed()) {
                    // extend both items to have a valid check basement
                    $extendedTriplePatternS = $this->commonNamespaces->extendUri($triplePattern[0]['s']);
                    $extendedSUri = $this->commonNamespaces->extendUri($stmt->getSubject()->getUri());

                    if ($extendedTriplePatternS == $extendedSUri) {
                        // get value of predicate and object
                        $pValue = $this->getNodeValue($stmt->getPredicate());
                        $oValue = $this->getNodeValue($stmt->getObject());

                        // add tuple
                        $entries[$pValue . $oValue] = array(
                            $triplePattern[0][$triplePattern[0]['p']] => $stmt->getPredicate(),
                            $triplePattern[0][$triplePattern[0]['o']] => $stmt->getObject()
                        );
                    }
                 }
            }
        }

        $entries = $this->applyFilters($entries, $filterInformation);

        return new SetResultImpl($entries);
    }
}

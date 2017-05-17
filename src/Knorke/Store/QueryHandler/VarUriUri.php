<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  ?s rdf:type foaf:Person .
 */
class VarUriUri extends AbstractQueryHandler
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
                        $entries[$sValue] = array(
                            $triplePattern[0][$triplePattern[0]['s']] => $stmt->getSubject(),
                        );
                    }
                }
            }
        }

        $entries = $this->applyFilters($entries, $filterInformation);

        return new SetResultImpl($entries);
    }
}

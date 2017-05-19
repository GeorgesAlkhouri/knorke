<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  ?s rdf:type foaf:Person .
 *
 * or
 *
 *  ?s rdfs:label "literal" .
 */
class VarUriLiteralOrUri extends AbstractQueryHandler
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
                // predicate condition
                $condition1 = $this->commonNamespaces->extendUri($stmt->getPredicate()->getUri())
                    == $this->commonNamespaces->extendUri($triplePattern[0]['p']);

                $condition2 = false;

                if ($stmt->getObject()->isNamed()) {
                    // object conditions
                    $oUri = $triplePattern[0]['o'];
                    $condition2 = $this->commonNamespaces->extendUri($oUri) == $stmt->getObject()->getUri();

                } elseif ($stmt->getObject()->isLiteral()) {
                    $condition2 = $stmt->getObject()->getValue() == $triplePattern[0]['o'];

                } else {
                    // invalid s p o found, go further
                    continue;
                }

                if ($condition1 && $condition2) {
                    $sValue = $this->getNodeValue($stmt->getSubject());
                    $entries[$sValue] = array(
                        $triplePattern[0][$triplePattern[0]['s']] => $stmt->getSubject(),
                    );
                }
            }
        }

        $entries = $this->applyFilters($entries, $filterInformation);

        return new SetResultImpl($entries);
    }
}

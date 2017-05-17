<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  foo:bar ?p ?o .
 */
class VarVarVar extends AbstractQueryHandler
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
        $addedStatementHashs = array();

        foreach ($statementsPerGraph as $graph => $statements) {
            foreach ($statements as $stmt) {
                $sValue = $this->getNodeValue($stmt->getSubject());
                $pValue = $this->getNodeValue($stmt->getPredicate());
                $oValue = $this->getNodeValue($stmt->getObject());

                // get value of predicate and object
                // only add new statements to result list
                if (false === isset($entries[$sValue . $pValue . $oValue])) {
                    // store subject-predicate-object triple
                    $entries[$sValue . $pValue . $oValue] = array(
                        $triplePattern[0]['s'] => $stmt->getSubject(),
                        $triplePattern[0]['p'] => $stmt->getPredicate(),
                        $triplePattern[0]['o'] => $stmt->getObject()
                    );
                }
            }
        }

        $entries = $this->applyFilters($entries, $filterInformation);

        return new SetResultImpl($entries);
    }
}

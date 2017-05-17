<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  _:foo ?p ?o
 */
class BlankVarVar extends AbstractQueryHandler
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
                $subject = $stmt->getSubject();
                if ($subject->isBlank()) {
                    if ('_:' !== substr($subject->getBlankId(), 0, 2)) {
                        $blankId = '_:'.$subject->getBlankId();
                    } else {
                        $blankId = $subject->getBlankId();
                    }
                    if (strtolower($blankId) == strtolower($triplePattern[0]['s'])) {
                        $entries[] = array(
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

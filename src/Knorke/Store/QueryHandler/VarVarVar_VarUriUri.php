<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  ?s ?p ?o .
 *  ?s rdf:type foaf:Person .
 */
class VarVarVar_VarUriUri extends AbstractQueryHandler
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
        foreach ($statementsPerGraph as $graph => $statements) {
            foreach ($statements as $statement) {
                $s = $statement->getSubject();
                $p = $statement->getPredicate();
                $o = $statement->getObject();
                // standard check
                if ($p->getUri() == $triplePattern[1]['p']
                    && $o->isNamed() && $o->getUri() == $triplePattern[1]['o']) {
                    $relevantS[$s->isNamed() ? $s->getUri() : $s->getBlankId()] = $s;
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
        }

        // 2. get all p and o for collected s
        foreach ($statementsPerGraph as $graph => $statements) {
            foreach ($statements as $statement) {
                $s = $statement->getSubject();
                $p = $statement->getPredicate();
                $o = $statement->getObject();

                // if statement object is shortened, extend it before put to result
                if ($statement->getObject()->isNamed()
                     && $this->commonNamespaces->isShortenedUri($statement->getObject())) {
                    $o = $this->nodeFactory->createNamedNode(
                        $this->commonNamespaces->extendUri($statement->getObject()->getUri())
                    );
                }

                if ($s->isNamed() && isset($relevantS[$s->getUri()])
                    || $s->isBlank() && isset($relevantS[$s->getBlankId()])) {
                    $entries[] = array(
                        $triplePattern[0]['s'] => $s,
                        $triplePattern[0]['p'] => $p,
                        $triplePattern[0]['o'] => $o
                    );
                }
            }
        }

        $entries = $this->applyFilters($entries, $filterInformation);

        return new SetResultImpl($entries);
    }
}

<?php

namespace Knorke\Store\QueryHandler;

use Saft\Sparql\Result\Result;
use Saft\Sparql\Result\SetResultImpl;

/**
 * Handles queries like:
 *  ?s ?p ?o .
 *  ?s rdf:type foaf:Person .
 *  ?s rdfs:label "foo" .
 */
class VarVarVar_VarUriUri_VarUriLiteral extends AbstractQueryHandler
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
        $relevantSFirstRound = array();
        $relevantS = array();

        // first triple pattern is s, p, o
        // ignore first pattern because it casts variables on s, p and o

        /*
         * second triple pattern: ?s rdf:type foaf:Person .
         */
        $extTriplePatternPUri = $this->commonNamespaces->extendUri($triplePattern[1]['p']);
        $extTriplePatternOUri = $this->commonNamespaces->extendUri($triplePattern[1]['o']);

        // check, which s has wanted p and o
        foreach ($statementsPerGraph as $graph => $statements) {
            foreach ($statements as $statement) {
                $s = $statement->getSubject();
                $p = $statement->getPredicate();
                $o = $statement->getObject();

                // p matches ...
                if ($extTriplePatternPUri == $this->commonNamespaces->extendUri($p->getUri())) {
                    // o is URI and matches too ...
                    if ($o->isNamed()
                        && $extTriplePatternOUri == $this->commonNamespaces->extendUri($o->getUri())) {
                        $relevantSFirstRound[$s->isNamed() ? $s->getUri() : $s->getBlankId()] = $s;
                    }
                }
            }
        }

        /*
         * check triple pattern: ?s foo:bar "literal" .
         */
        $extTriplePatternPUri = $this->commonNamespaces->extendUri($triplePattern[2]['p']);
        foreach ($statementsPerGraph as $graph => $statements) {
            foreach ($statements as $statement) {
                $s = $statement->getSubject();
                $p = $statement->getPredicate();
                $o = $statement->getObject();

                // only take entries, which are already marked as relevant
                if (false === isset($relevantSFirstRound[$s->isNamed() ? $s->getUri() : $s->getBlankId()])) {
                    continue;
                }

                // p matches ...
                if ($extTriplePatternPUri == $this->commonNamespaces->extendUri($p->getUri())) {
                    // o is URI and matches too ...
                    if ($o->isLiteral() && $o->getValue() == $triplePattern[2]['o']) {
                        $relevantS[$s->isNamed() ? $s->getUri() : $s->getBlankId()] = $s;
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
                if ($statement->getObject()->isNamed()) {
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

<?php

namespace Knorke\Rdf;

use Saft\Rdf\StatementImpl;

/**
 *
 */
class HashableStatementImpl extends StatementImpl implements HashableStatement
{
    public function hash($algorithm = 'sha256')
    {

        $combined = (string)$this->subject . " " . (string)$this->predicate . " " . (string)$this->object;
        if ($this->isQuad())
        {
            $combined += " " . (string)$this->graph;
        }

        return hash($algorithm, $combined);
    }
}

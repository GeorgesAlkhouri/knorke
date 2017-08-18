<?php

namespace Knorke\Rdf;

use Saft\Rdf\StatementImpl;

/**
 *
 */
class HashableStatementImpl extends StatementImpl implements HashableStatement
{

  /**
   * Computes a hash from a triple or quad by combining s p o [g].
   *
   * @param $algorithm Name of the hash algorithm.
   * @return hash
   */
    public function hash($algorithm = 'sha256')
    {

        $combined = (string)$this->subject . " " . (string)$this->predicate . " " . (string)$this->object;
        if ($this->isQuad())
        {
            $combined = $combined . " " . (string)$this->graph;
        }

        return hash($algorithm, $combined);
    }
}

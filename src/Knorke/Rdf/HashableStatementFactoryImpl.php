<?php

namespace Knorke\Rdf;

use Saft\Rdf\StatementFactory;
use Saft\Rdf\Node;

class HashableStatementFactoryImpl implements StatementFactory
{
    public function createStatement(Node $subject, Node $predicate, Node $object, Node $graph = null)
    {
        return new HashableStatementImpl($subject, $predicate, $object, $graph);
    }
}

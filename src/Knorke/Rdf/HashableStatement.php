<?php

namespace Knorke\Rdf;

use Saft\Rdf\Statement;

interface HashableStatement extends Statement
{
    public function hash($algorithm = 'sha256', $considerGraphUri = false);
}

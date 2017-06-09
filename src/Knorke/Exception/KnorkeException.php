<?php

namespace Knorke\Exception;

/**
 * @codeCoverageIgnore
 */
class KnorkeException extends \Exception
{
    protected $payload;

    public function getPayload()
    {
        return $this->payload;
    }

    public function setPayload($payload)
    {
        $this->payload = $payload;
    }
}

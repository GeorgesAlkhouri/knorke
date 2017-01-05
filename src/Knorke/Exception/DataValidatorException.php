<?php

namespace Knorke\Exception;

/**
 * Used for data validator tasks.
 */
class DataValidatorException extends \Exception
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

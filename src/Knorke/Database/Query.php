<?php

namespace Knorke\Database;

use Knorke\FiniteStateMachine\Machine;

/**
 *
 */
class Query extends Machine
{
    public function __construct()
    {
        $this->registerCallbackOn('prepare', array($this, 'prepare'));
        $this->registerCallbackOn('send_query', array($this, 'sendQuery'));
        $this->registerCallbackOn('__exception', array($this, 'handleException'));
    }

    public function prepare($info, $user, $pass, $host, $db)
    {
        echo PHP_EOL . 'prepare called with '. $user . ', '. $pass .', '. $host .', '. $db;
    }

    public function sendQuery($info, $query)
    {
        echo PHP_EOL . 'sendQuery: '. $query;
    }

    public function handleResult($info, $result)
    {
        echo PHP_EOL . 'handleResult: '. $result;
    }

    public function handleEmptyResult($result)
    {

    }

    public function handleErrorOrException($result)
    {

    }
}

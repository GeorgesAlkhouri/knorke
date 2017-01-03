<?php

/**
 * Demonstrates usage of finite state machine
 */

require __DIR__ .'/../../vendor/autoload.php';

use Knorke\FiniteStateMachine\Machine;

/**
 *
 */
class FSMDemo extends Machine
{
    public function __construct()
    {
        $this->registerCallbackOn('prepare', array($this, 'prepare'));
        $this->registerCallbackOn('send_query', array($this, 'sendQuery'));
        $this->registerCallbackOn('__on_exception', array($this, 'handleException'));
    }

    public function prepare($info, $user, $pass, $host, $db)
    {
        echo PHP_EOL . 'prepare called with '. $user . ', '. $pass .', '. $host .', '. $db;
    }

    public function sendQuery($info, $query)
    {
        throw new \Exception('Invalid query');
    }

    public function handleException($info, \Exception $e)
    {
        echo PHP_EOL . 'Handle exception ' . $e->getMessage();
    }
}

/*
 * Prepare machine
 */
$queryMachine = new FSMDemo(); // its a finite state machine

$queryMachine->addTransition('prepare',    array('from' => 'started',  'to' => 'prepared'));
$queryMachine->addTransition('send_query', array('from' => 'prepared', 'to' => 'query_sent'));

/*
 * Turn on engine
 */
// prepare database connection
$queryMachine->transition('prepare', array('user', 'pass', 'host', 'db'));

// send query
$queryMachine->transition('send_query', array('query'));

echo PHP_EOL;

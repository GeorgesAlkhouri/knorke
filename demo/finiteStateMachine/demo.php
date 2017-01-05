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
    protected $x = 42;

    public function __construct()
    {
        $this->registerCallbackOn('prepare', array($this, 'prepare'));
        $this->registerCallbackOn('send_query', array($this, 'sendQuery'));
        $this->registerCallbackOn('handle_result', array($this, 'handleResult'));
    }

    public function getInternalX()
    {
        return $this->x;
    }

    public function prepare($info, $user, $pass, $host, $db)
    {
        echo PHP_EOL . 'prepare called with (given from outside): '. $user . ', '. $pass .', '. $host .', '. $db;
    }

    public function sendQuery($info, $query)
    {
        echo PHP_EOL . 'send query (given from outside):';
        var_dump($query);
    }

    public function handleResult($info, $result)
    {
        echo PHP_EOL . 'handle result (given from inside):';
        var_dump($result);
    }

    /**
     * Attach callbacks on transitions. Adapted for query related purposes.
     *
     * @param string $transitionName
     * @param callable $callback
     */
    public function registerCallbackOn($transitionName, callable $callback)
    {
        // create a wrapper, which adapts the source callback parameter list
        if ('handle_result' == $transitionName) {
            $self = $this;
            $this->callbacks[$transitionName][] = function() use ($self, $callback) {
                $parameter = func_get_args();
                $parameter[] = $self->getInternalX();

                call_user_func_array(
                    $callback,
                    $parameter
                );
            };
        } else {
            parent::registerCallbackOn($transitionName, $callback);
        }
    }
}

/*
 * Prepare machine
 */
$queryMachine = new FSMDemo(); // its a finite state machine

$queryMachine->addTransition('prepare',       array('from' => 'started',    'to' => 'prepared'));
$queryMachine->addTransition('send_query',    array('from' => 'prepared',   'to' => 'query_sent'));
$queryMachine->addTransition('handle_result', array('from' => 'query_sent', 'to' => 'handled_result'));

$queryMachine->registerCallbackOn('handle_result', function($transitionInfo, $result) {
    echo PHP_EOL . '...... outside callback handle_result';
    var_dump($result);
});

/*
 * Turn on the engine
 */
// prepare database connection
$queryMachine->transition('prepare', array('user', 'pass', 'host', 'db'));

// send query
$queryMachine->transition('send_query', array('query'));

// handle request
$queryMachine->transition('handle_result');

echo PHP_EOL;

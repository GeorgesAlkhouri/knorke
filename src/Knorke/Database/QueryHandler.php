<?php

namespace Knorke\Database;

use Knorke\FiniteStateMachine\Machine;

/**
 *
 */
class QueryHandler extends Machine
{
    protected $result = null;

    public function __construct()
    {
        // define transitions
        $this->addTransition('prepare',    array('from' => 'started',  'to' => 'prepared'));
        $this->addTransition('send_query', array('from' => 'prepared', 'to' => 'sent_query'));
        $this->addTransition('handle_result', array('from' => 'sent_query', 'to' => 'result_handled'));

        // define internal callbacks
        $this->registerCallbackOn('prepare', array($this, 'prepare'));
        $this->registerCallbackOn('send_query', array($this, 'sendQuery'));
        $this->registerCallbackOn('handle_result', array($this, 'handleResult'));
        $this->registerCallbackOn('__exception', array($this, 'handleErrorOrException'));
    }

    public function getResult()
    {
        return $this->result;
    }

    public function prepare(array $transitionInfo, array $connectionParameter)
    {

    }

    public function sendQuery(array $transitionInfo, $query)
    {

    }

    public function handleResult(array $transitionInfo, $result)
    {

    }

    public function handleErrorOrException(array $transitionInfo, $result)
    {

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
                $parameter[] = $self->getResult();

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

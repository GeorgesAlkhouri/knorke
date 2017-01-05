<?php

namespace Knorke\FiniteStateMachine;

/**
 *
 */
class Machine
{
    protected $callbacks = array();
    protected $currentState = null;
    protected $transitions = array();

    public function addTransition($name, $fromTo)
    {
        $this->transitions[$name] = $fromTo;
    }

    public function callUserFunction($callback, $name, array $parameter = null)
    {
        if (is_array($callback) || is_callable($callback)) {
            // build function parameter list
            $fullParameterArray = array();
            $fullParameterArray[] = array('transition' => $name); // transition related data
            if (0 < count($parameter)) {
                $fullParameterArray = array_merge($fullParameterArray, $parameter);
            }

            call_user_func_array(
                $callback,
                $fullParameterArray
            );
        } else {
            var_dump($callback);
            throw new \Exception('Invalid callback given.');
        }
    }

    public function getCurrentState()
    {
        return $this->currentState;
    }

    public function setCurrentState($state)
    {
        $this->currentState = $state;
    }

    /**
     * Change the state of the machine from X to Y. Because of that, several of callbacks may be called with parameters
     * given from the outside and the inside.
     *
     * @param string $name
     * @param array $parameter Parameter given from the outside. Optional, default is array()
     */
    public function transition($name, array $parameter = null)
    {
        $this->setCurrentState($this->transitions[$name]['to']);

        // call registered callbacks
        try {
            if (isset($this->callbacks[$name])) {
                foreach ($this->callbacks[$name] as $callback) {
                    $this->callUserFunction($callback, $name, $parameter);
                }
            }

        // in case an exception was thrown, catch it and stop further execution of the machine
        } catch (\Exception $e) {
            if (isset($this->callbacks['__on_exception'])) {
                foreach ($this->callbacks['__on_exception'] as $callback) {
                    $this->callUserFunction($callback, '__on_exception', array($e));
                }
            } else {
                // TODO report depending on the log-level
                echo PHP_EOL . 'Exception: '. $e->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * Attach callbacks on transitions.
     *
     * @param string $transitionName
     * @param array|callable $callback
     */
    public function registerCallbackOn($transitionName, $callback)
    {
        if (!isset($this->callbacks[$transitionName])) {
            $this->callbacks[$transitionName] = array();
        }

        $this->callbacks[$transitionName][] = $callback;
    }
}

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

    public function callUserFunction($callback, $name, $parameter)
    {
        if (is_array($callback) || is_callable($callback)) {
            // build function parameter list
            $fullParameterArray = array();
            $fullParameterArray[] = array('transition' => $name);
            $fullParameterArray = array_merge($fullParameterArray, $parameter);

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
     * @param string $name
     * @param array $parameter optional, default is array()
     */
    public function transition($name, array $parameter = array())
    {
        $this->setCurrentState($this->transitions[$name]['to']);

        // call registered callbacks
        try {
            if (isset($this->callbacks[$name])) {
                foreach ($this->callbacks[$name] as $callback) {
                    $this->callUserFunction($callback, $name, $parameter);
                }
            }
        } catch (\Exception $e) {
            if (isset($this->callbacks['__on_exception'])) {
                foreach ($this->callbacks['__on_exception'] as $callback) {
                    $this->callUserFunction($callback, '__on_exception', array($e));
                }
            } else {
                // TODO report depending on the log-level
                echo PHP_EOL . 'Exception: '. $e->getMessage();
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

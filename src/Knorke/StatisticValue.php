<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
use Saft\Store\Store;

class StatisticValue
{
    protected $commonNamespaces;
    protected $mapping;
    protected $store;

    /**
     * @param Store $store
     * @param CommonNamespaces $commonNamespaces
     * @param array $mapping
     */
    public function __construct(Store $store, CommonNamespaces $commonNamespaces, array $mapping)
    {
        $this->commonNamespaces = $commonNamespaces;
        $this->mapping = $mapping;
        $this->store = $store;
    }

    /**
     * Computes all depending values based on the given $mapping of non-depending values.
     *
     * @return array Complete mapping with computed values.
     * @throws KnorkeException if non-depending values have no mapping
     */
    public function compute()
    {
        $computedValues = array();

        // gather all SPO for StasticValue instances
        $statisticValueResult = $this->store->query(
            'SELECT * WHERE {
                ?s ?p ?o.
                ?s rdf:type kno:StatisticValue .
            }'
        );

        // collect subjects of all statistic value instances
        // create datablank instances for each statistic value for easier usage later on
        $statisticValues = array();
        foreach ($statisticValueResult as $entry) {
            $subjectUri = $entry['s']->getUri();
            if (false == isset($statisticValues[$subjectUri])) {
                $statisticValues[$subjectUri] = new DataBlank($this->commonNamespaces);
                $statisticValues[$subjectUri]->initBySetResult($statisticValueResult, $subjectUri);
            }
        }

        // check that all non-depending values were defined
        foreach ($statisticValues as $uri => $statisticValue) {
            // check for values which have no computationOrder property but are part of the mapping
            if (false === isset($statisticValue['kno:computationOrder'])
                && false === isset($this->mapping[$uri])
                && false === isset($this->mapping[$this->commonNamespaces->shortenUri($uri)])) {
                $e = new KnorkeException('Statistic value ' . $uri . ' is non-depending, but has no mapping.');
                $e->setPayload($statisticValue);
                throw $e;
            }
        }

        // compute computationOrder for each statistical value
        $statisticalValuesWithCompOrder = array();
        foreach ($statisticValues as $uri => $statisticValue) {
            if (isset($statisticValue['kno:computationOrder'])) {
                $statisticalValuesWithCompOrder[$uri] = $this->getComputationOrderFor($uri, $statisticValues);
            }
        }

        $computedValues = $this->mapping;

        // go through all statistic value instances with computationOrder property and compute related values
        foreach ($statisticalValuesWithCompOrder as $uri => $computationOrder) {
            // assumption: properties are something like "kno:_1" and ordered, therefore we ignore properties later on
            // store computed value for statisticValue instance
            $computedValues[$uri] = $this->executeComputationOrder(
                $computationOrder,              // rule how to compute
                $computedValues,                // already computed stuff from before
                $statisticalValuesWithCompOrder // computation order per statistical value URI
            );
        }

        return $computedValues;
    }

    /**
     * @param string|float $value1
     * @param string $operation Either +, -, * or /
     * @param float $value1
     */
    public function computeValue($value1, $operation, $value2)
    {
        // if $value1 is a string, we assume its a date like '2017-01-01'
        if (is_string($value1)) {
            $dateTime = $value1 . ' 00:00:00';
            $timestamp = strtotime($dateTime);

            // stop here, if value2 is crap
            $value2 = (int)$value2;
            if (0 == $value2) {
                return null;
            }

            // $value2 is the number of days we go forward or backward from the given date
            switch ($operation) {
                case '+': return date('Y-m-d', ($timestamp+(86400*$value2)));
                case '-': return date('Y-m-d', ($timestamp-(86400*$value2)));
                default: return null;
            }

        // value1 and value2 are both floats and can therefore be computed directly
        } else {
            switch($operation) {
                case '+': return $value1 + (float)$value2;
                case '*': return $value1 * (float)$value2;
                case '-': return $value1 - (float)$value2;
                case '/': return $value1 / (float)$value2;
                default: return null;
            }
        }
    }

    /**
     * @param array $computationOrder Rules to compute one statistical value.
     * @param array $computedValues Array with URI as key and according computed value of already computed values.
     * @param array $statisticalValuesWithCompOrder
     * @return float if computation works well
     * @throws KnorkeException if invalide rule was detected.
     * @todo handle the case that a required value is not available yet
     */
    public function executeComputationOrder(
        array $computationOrder,
        array $computedValues,
        array $statisticalValuesWithCompOrder
    ) {
        $lastComputedValue = null;

        foreach ($computationOrder as $computationRule) {
            $value1 = null;
            $value2 = null;

            preg_match('/\[(.*?)\]([*\/+-]{1})(.*)/', $computationRule, $doubleValueMatch);
            preg_match('/([*|\/|+|-])(.*)/', $computationRule, $singleValueMatch);

            // found match for 2 values with an operation to compute (like a+1). can only be as the first entry
            if (isset($doubleValueMatch[1]) && null == $lastComputedValue) {
                if (isset($computedValues[$doubleValueMatch[1]])) {
                    $value1 = (float)$computedValues[$doubleValueMatch[1]];
                } else {
                    // get value because it wasn't computed yet
                    $value1 = $this->executeComputationOrder(
                        $statisticalValuesWithCompOrder[$doubleValueMatch[1]],
                        $computedValues,
                        $statisticalValuesWithCompOrder
                    );
                }
                $operation = $doubleValueMatch[2];

            // found match for 1 value with 1 operation to compute (like +2). here we use the result of the computation
            // last round
            } elseif (isset($singleValueMatch[1]) && null !== $lastComputedValue) {
                $value1 = (float)$lastComputedValue;
                $operation = $singleValueMatch[1];
            }

            // value 2
            // check if value2 contains letters; if so, its an URI, if not, assume its a simple number
            if (0 < preg_match('/([a-zA-Z:])/', $singleValueMatch[2], $value2Match)) {
                if (isset($computedValues[$singleValueMatch[2]])) {
                    $value2 = $computedValues[$singleValueMatch[2]];
                } else {
                    // get value because it wasn't computed yet
                    $value2 = $this->executeComputationOrder(
                        $statisticalValuesWithCompOrder[$singleValueMatch[2]],
                        $computedValues,
                        $statisticalValuesWithCompOrder
                    );
                }
            } else {
                $value2 = $singleValueMatch[2];
            }

            // computation
            $lastComputedValue = $this->computeValue($value1, $operation, $value2);

            if (null !== $lastComputedValue) continue;

            // if we reach this here, something went wrong. so always execute continue after you are "finished" with a
            // computation step to go to the next rule.

            /*
             * invalid rule found
             */
            $e = new KnorkeException(
                'Invalid computationRule found or you tried to use 2 value computation but had a result already.'
            );
            $e->setPayload(array(
                'computed_values' => $computedValues,
                'computation_rule' => $computationRule,
                'last_computed_value' => $lastComputedValue,
                'store' => $this->store,
            ));
            throw $e;
        }

        return $lastComputedValue;
    }

    /**
     * @param string $statisticValueUri
     * @param array $statisticValues Array of arrays which container references (kno:computationOrder) to blank nodes.
     * @return null|array Array if an order was found, null otherwise.
     */
    public function getComputationOrderFor($statisticValueUri, array $statisticValues)
    {
        foreach ($statisticValues as $uri => $value) {
            if ($uri == $statisticValueUri) {
                $result = $this->store->query(
                    'SELECT * WHERE {'. $value['kno:computationOrder'] .' ?p ?o.}'
                );
                $computationOrderBlank = new DataBlank($this->commonNamespaces);
                $computationOrderBlank->initBySetResult($result, $value['kno:computationOrder']);

                // order entries by key
                $computationOrder = $computationOrderBlank->getArrayCopy();
                ksort($computationOrder);

                return $computationOrder;
            }
        }

        return null;
    }
}

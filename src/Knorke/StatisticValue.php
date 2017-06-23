<?php

namespace Knorke;

use Knorke\Exception\KnorkeException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\RdfHelpers;
use Saft\Store\Store;

class StatisticValue
{
    protected $commonNamespaces;
    protected $dataBlankHelper;
    protected $graphs;
    protected $mapping;
    protected $rdfHelpers;
    protected $store;

    /**
     * @param Store $store
     * @param CommonNamespaces $commonNamespaces
     * @param RdfHelpers $rdfHelpers
     * @param DataBlankHelper $dataBlankHelper
     * @param array $graphs
     * @param array $mapping
     */
    public function __construct(
        Store $store,
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers,
        DataBlankHelper $dataBlankHelper,
        array $graphs,
        array $startMapping = array()
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->graphs = $graphs;
        $this->startMapping = $startMapping;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
    }

    /**
     * Computes all depending values based on the given $mapping of non-depending values.
     *
     * @param array $valuesToCompute
     * @return array Complete mapping with computed values.
     * @throws KnorkeException if mapping is empty
     * @throws KnorkeException if non-depending values have no mapping
     */
    public function compute(array $valuesToCompute)
    {
        if (0 == count($this->startMapping)) {
            throw new KnorkeException('Empty start mapping found.');
        } elseif (0 == count($valuesToCompute)) {
            throw new KnorkeException('Parameter $valuesToCompute is empty.');
        }

        $statisticValues = array();

        // gather info for given values to compute
        foreach ($valuesToCompute as $valueUri) {
            // extend URI
            $valueUri = $this->commonNamespaces->extendUri($valueUri);
            $shortValueUri = $this->commonNamespaces->shortenUri($valueUri);

            // create DataBlank instance which represents given value
            $statisticValues[$shortValueUri] = $this->dataBlankHelper->load($valueUri);
        }

        $computedValues = array();

        // check that all non-depending values were defined
        foreach ($statisticValues as $uri => $statisticValue) {
            // check for values which have no computationOrder property but are part of the mapping
            if (false === isset($statisticValue['kno:computation-order'])
                && false === isset($this->startMapping[$this->commonNamespaces->shortenUri($uri)])
                && false === isset($this->startMapping[$this->commonNamespaces->extendUri($uri)])) {
                $e = new KnorkeException(
                    'Statistic value ' . $uri . ' is part of the start mapping, but has no value.'
                );
                $e->setPayload($statisticValue);
                throw $e;
            }
        }

        // shorten all URI keys if neccessary
        $computedValues = array();
        foreach ($this->startMapping as $uri => $value) {
            $computedValues[$this->commonNamespaces->shortenUri($uri)] = $value;
        }

        // go through all statistic value instances with computationOrder property and compute related values
        foreach ($statisticValues as $uri => $statisticValue) {
            // assumption: properties are something like "kno:_1" and ordered, therefore we ignore properties later on.
            // store computed value for statisticValue instance
            $computedValues[$this->commonNamespaces->shortenUri($uri)] = $this->executeComputationOrder(
                $statisticValue['kno:computation-order']['_idUri'],
                $computedValues,                                    // already computed stuff from before
                $statisticValues                                    // computation order per statistical value URI
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
        if (is_string($value1) && is_numeric($value2)) {
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

        // if $value1 amd $value2 are strings
        } elseif (is_string($value1) && is_string($value2)) {
            $dateTime1 = $value1 . ' 00:00:00';
            $timestamp1 = strtotime($dateTime1);

            $dateTime2 = $value2 . ' 00:00:00';
            $timestamp2 = strtotime($dateTime2);

            // operation needs to be MINUS, so we remove the second timestamp from the first
            // and will receive the number of days as difference
            switch ($operation) {
                case '-': return ($timestamp1-$timestamp2)/(60*60*24);
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
     * @param string $computationOrderUri
     * @param array $computedValues Array with URI as key and according computed value of already computed values.
     * @param array $statisticalValuesWithCompOrder
     * @return float if computation works well
     * @throws KnorkeException if invalide rule was detected.
     * @todo handle the case that a required value is not available yet
     * @todo move computation rules to separate function or class for easier maintenance
     */
    public function executeComputationOrder(
        string $computationOrderUri,
        $computedValues,
        $statisticalValuesWithCompOrder
    ) {
        $computationOrder = $this->dataBlankHelper->load($computationOrderUri);
        $lastComputedValue = null;

        foreach ($computationOrder as $key => $computationRule) {
            if ('_idUri' == $key) continue;

            $value1 = null;
            $value2 = null;
            $operation = null;

            // ROUNDUP: if its a float, always round to the next higher number (e.g. 1.1 => 2)
            if ('ROUNDUP' == $computationRule) {
                $precision = 0;
                $fig = (int)str_pad('1', $precision, '0');
                $lastComputedValue = (ceil($lastComputedValue * $fig) / $fig);

            // MAX(result,..): checks result and decides to keep result or to use the alternative, if its higher
            } elseif ('MAX(result' == substr($computationRule, 0, 10)) {
                // get max alternative
                preg_match('/MAX\(result,([0-9\s]+)\)/', $computationRule, $maxMatches);
                // use alternative over last computed value, if alternative is heigher
                if (isset($maxMatches[1]) && $maxMatches[1] > $lastComputedValue) {
                    $lastComputedValue = $maxMatches[1];
                }

            // IF clause: set value depending on an if-clause, e.g. IF([stat:1] > 0, 1, 0)
            // TODO implement gathering referenced value, if not computed yet
            } elseif (preg_match('/IF\(\[(.*)\]\s*([>|<])\s*([0-9]+),\s*([0-9]+),\s*([0-9]+)\)/', $computationRule, $ifMatch)
                && isset($ifMatch[1])) {
                $statisticValueUri = $this->commonNamespaces->shortenUri($ifMatch[1]); // e.g. stat:2
                $ifOperation = $ifMatch[2];                                            // e.g. >
                $ifConstraintValue = (float)$ifMatch[3];                               // e.g. 0
                $ifValueOdd = $ifMatch[4];                                             // e.g. 1 (if true)
                $ifValueEven = $ifMatch[5];                                            // e.g. 0 (if false)

                // <
                if ('<' == $ifOperation && $computedValues[$statisticValueUri] < $ifConstraintValue) {
                    $lastComputedValue = (float)$ifValueOdd;
                // >
                } elseif ('>' == $ifOperation && $computedValues[$statisticValueUri] > $ifConstraintValue) {
                    $lastComputedValue = (float)$ifValueOdd;
                // =
                } else {
                    $lastComputedValue = (float)$ifValueEven;
                }

            // Reuse existing value
            // TODO implement gathering referenced value, if not computed yet
            } elseif (preg_match('/^\[(.*?)\]$/', $computationRule, $reuseMatch) && isset($reuseMatch[1])) {
                $lastComputedValue = $computedValues[$reuseMatch[1]];

            // parse and handle rule
            } else {
                preg_match('/\[(.*?)\]([*\/+-]{1})(.*)/', $computationRule, $doubleValueMatch);
                preg_match('/^([*|\/|+|-])(.*)/', $computationRule, $singleValueMatch);

                /*
                 * found match for 2 values with an operation to compute (like a+1). can only be as the first entry
                 */
                if (isset($doubleValueMatch[1]) && null == $lastComputedValue) {
                    $statisticValue1Uri = $this->commonNamespaces->shortenUri($doubleValueMatch[1]);

                    if (isset($computedValues[$statisticValue1Uri])) {
                        $value1 = $computedValues[$statisticValue1Uri];
                    } elseif (isset($statisticalValuesWithCompOrder[$statisticValue1Uri])) {
                        // get value because it wasn't computed yet
                        $value1 = $this->executeComputationOrder(
                            $statisticalValuesWithCompOrder[$statisticValue1Uri]['kno:computation-order']['_idUri'],
                            $computedValues,
                            $statisticalValuesWithCompOrder
                        );
                    } else {
                        $e = new KnorkeException('Parameter computation order is undefined.');
                        $e->setPayload(array(
                            'array_with_comp_order' => $statisticalValuesWithCompOrder,
                            'key_to_access_array' => $statisticValue1Uri
                        ));
                        throw $e;
                    }

                    $operation = $doubleValueMatch[2];
                    $value2 = $doubleValueMatch[3];

                /*
                 * found match for 1 value with 1 operation to compute (like +2).
                 * here we use the result of the computation last round as value1
                 */
                } elseif (isset($singleValueMatch[1]) && null !== $lastComputedValue) {
                    $value1 = $lastComputedValue;
                    $operation = $singleValueMatch[1];
                    $value2 = $singleValueMatch[2];
                }

                // if value2 is not a number, assume its an URI
                if (false === is_numeric($value2) && null !== $value2) {
                    $value2 = $this->commonNamespaces->shortenUri($value2);

                    // get value because it wasn't computed yet
                    if (false == isset($computedValues[$value2])) {
                        $value2 = $this->executeComputationOrder(
                            $statisticalValuesWithCompOrder[$value2]['kno:computation-order']['_idUri'],
                            $computedValues,
                            $statisticalValuesWithCompOrder
                        );
                    } else {
                        $value2 = $computedValues[$value2];
                    }
                }

                // computation
                $lastComputedValue = $this->computeValue($value1, $operation, $value2);
            }

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
     * @param array $statisticValues Array of arrays which contains references (kno:computation-order) to blank nodes.
     * @return null|array Array if an order was found, null otherwise.
     */
    public function getComputationOrderFor($statisticValueUri, array $statisticValues)
    {
        $statisticValueUri = $this->commonNamespaces->shortenUri($statisticValueUri);

        foreach ($statisticValues as $uri => $value) {
            $uri = $this->commonNamespaces->shortenUri($uri);
            if ($uri == $statisticValueUri) {
                $computationOrderBlank = $this->dataBlankHelper->load($value['kno:computation-order']['_idUri']);

                // order entries by key
                $computationOrder = $computationOrderBlank->getArrayCopy();
                ksort($computationOrder);

                // extend all URIs used
                foreach ($computationOrder as $key => $string) {
                    unset($computationOrder[$key]);
                    $computationOrder[$key] = $this->commonNamespaces->shortenUri($string);
                }

                return $computationOrder;
            }
        }

        return null;
    }

    /**
     * Updates stored mapping.
     *
     * @param array $mapping
     */
    public function setStartMapping(array $mapping)
    {
        $this->startMapping = $mapping;
    }
}

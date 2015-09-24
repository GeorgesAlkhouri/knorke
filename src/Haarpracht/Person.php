<?php

namespace Haarpracht;

/**
 *
 */
class Person
{
    protected $graphUri;
    protected $store;

    function __construct(\Saft\Store\Store $store, $graphUri)
    {
        $this->graphUri = $graphUri;
        $this->store = $store;
    }

    protected function getRestrictions($propertyUri)
    {
        $result = $this->store->query('
            SELECT ?restriction ?p ?o
              FROM <'. $this->graphUri .'>
             WHERE {
                 <'. $propertyUri .'> <http://localhost/haarpracht/hasRestriction> [
                     ?p ?o
                 ].
             }
        ');

        return new \Saft\Rapid\Blank($result);
    }

    public function validateData($data)
    {
        $result = $this->store->query('
            SELECT ?p ?o
              FROM <'. $this->graphUri .'>
             WHERE {
                 <http://localhost/haarpracht/person> ?p ?o.
             }
        ');

        $blank = new \Saft\Rapid\Blank($result);

        // go through all hasProperty relations
        foreach ($blank['http://localhost/haarpracht/hasProperty'] as $propertyUri) {
            $shortenedPropertyUri = str_replace('http://localhost/haarpracht/', 'haar:', $propertyUri);

            $restrictions = $this->getRestrictions($propertyUri);

            /**
             * checks minimum double value
             */
            if (isset($restrictions['http://localhost/haarpracht/minimumDoubleValue'])) {
                $value = (double)$data[$shortenedPropertyUri];
                $minimumValue = $restrictions['http://localhost/haarpracht/minimumDoubleValue'];

                if ($minimumValue > $value) {
                    throw new \Exception('Value lower as '. $minimumValue .' : '. $value);
                }
            }

            /**
             * checks maximum double value
             */
            if (isset($restrictions['http://localhost/haarpracht/maximumDoubleValue'])) {
                $value = (double)$data[$shortenedPropertyUri];
                $maximumValue = $restrictions['http://localhost/haarpracht/maximumDoubleValue'];

                if ($maximumValue < $value) {
                    throw new \Exception('Value higher as '. $maximumValue .' : '. $value);
                }
            }
        }
    }
}

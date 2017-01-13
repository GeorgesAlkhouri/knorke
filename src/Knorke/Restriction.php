<?php

namespace Knorke;

use Saft\Store\Store;

/**
 *
 */
class Restriction
{
    protected $commonNamespaces;
    protected $store;

    public function __construct(Store $store, CommonNamespaces $commonNamespaces)
    {
        $this->commonNamespaces = $commonNamespaces;
        $this->store = $store;
    }

    /**
     * @param string $resourceUri
     */
    public function getRestrictionsForResource($resourceUri)
    {
        $blank = new DataBlank($this->commonNamespaces);
        $blank->initBySetResult($this->store->query('SELECT * WHERE {<'. $resourceUri .'> ?p ?o.}'), $resourceUri);

        /*
         * if its a proxy resource which inherits from another, get the properties of the other one.
         */
        if (null !== $blank->get('kno:inheritsAllPropertiesOf')) {
            // get infos from the other resource
            $foreignResourceUri = $blank->get('kno:inheritsAllPropertiesOf');
            $foreignResourceInfo = $this->store->query('SELECT * WHERE {<'. $foreignResourceUri .'> ?p ?o.}');
            $foreignResourceBlank = new DataBlank($this->commonNamespaces);
            $foreignResourceBlank->initBySetResult($foreignResourceInfo, $foreignResourceUri);
            // copy property-value combination into blank instance
            foreach ($foreignResourceBlank as $property => $value) {
                $blank[$property] = $value;
            }
        }

        /*
         * if its a list and the order of elements is fixed
         */
        if (null !== $blank->get('kno:restrictionOrder')) {
            $orderResource = $blank->get('kno:restrictionOrder');
            $orderInformation = $this->store->query('SELECT * WHERE {'. $orderResource .' ?p ?o.}');
            $orderBlank = new DataBlank($this->commonNamespaces);
            $orderBlank->initBySetResult($orderInformation, $orderResource);
            $orderArray = $orderBlank->getArrayCopy();
            ksort($orderArray); // sort by key
            $blank['kno:restrictionOrder'] = $orderArray;
        }

        return $blank;
    }
}

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

    public function getRestrictionsForResource($resourceUri)
    {
        $blank = new DataBlank($this->commonNamespaces);
        $blank->initBySetResult($this->store->query('SELECT * WHERE {<'. $resourceUri .'> ?p ?o.}'), $resourceUri);

        // if the filter inherits properties from another resource, copy their properties into the filter
        if (isset($blank['kno:inheritsAllPropertiesOf'])) {
            // get infos from the other resource
            $foreignResourceUri = $blank['kno:inheritsAllPropertiesOf'];
            $foreignResourceInfo = $this->store->query('SELECT * WHERE {<'. $foreignResourceUri .'> ?p ?o.}');
            $foreignResourceBlank = new DataBlank($this->commonNamespaces);
            $foreignResourceBlank->initBySetResult($foreignResourceInfo, $foreignResourceUri);
            // copy property-value combination into blank instance
            foreach ($foreignResourceBlank as $property => $value) {
                $blank[$property] = $value;
            }
        }

        // if its defined, it references a resource
        if (isset($blank['kno:restrictionOrder'])) {
            $orderResource = $blank['kno:restrictionOrder'];
            $orderInformation = $this->store->query('SELECT * WHERE {'. $orderResource .' ?p ?o.}');
            $orderBlank = new DataBlank($this->commonNamespaces);
            $orderBlank->initBySetResult($orderInformation, $orderResource);
            $orderArray = $orderBlank->getArrayCopy();
            ksort($orderArray);
            $blank['kno:restrictionOrder'] = $orderArray;
        }

        return $blank;
    }
}

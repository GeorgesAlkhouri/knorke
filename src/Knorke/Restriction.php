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

    public function getRestrictionsForResource($resourceUri, array $dataBlankOptions = array())
    {
        $options = array_merge(
            array('use_prefixed_predicates' => true, 'use_prefixed_objects' => true),
            $dataBlankOptions
        );
        $blank = new DataBlank($this->commonNamespaces, $options);
        $blank->initBySetResult($this->store->query('SELECT * WHERE {<'. $resourceUri .'> ?p ?o.}'), $resourceUri);

        if (null !== $blank->get('kno:inheritsAllPropertiesOf')) {
            // get infos from the other resource
            $foreignResourceUri = $blank->get('kno:inheritsAllPropertiesOf');
            $foreignResourceInfo = $this->store->query('SELECT * WHERE {<'. $foreignResourceUri .'> ?p ?o.}');
            $foreignResourceBlank = new DataBlank($this->commonNamespaces, $dataBlankOptions);
            $foreignResourceBlank->initBySetResult($foreignResourceInfo, $foreignResourceUri);
            // copy property-value combination into blank instance
            foreach ($foreignResourceBlank as $property => $value) {
                $blank[$property] = $value;
            }
        }

        if (null !== $blank->get('kno:restrictionOrder')) {
            $orderResource = $blank->get('kno:restrictionOrder');
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

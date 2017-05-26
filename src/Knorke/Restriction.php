<?php

namespace Knorke;

use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\RdfHelpers;
use Saft\Store\Store;

/**
 *
 */
class Restriction
{
    protected $commonNamespaces;
    protected $dataBlankHelper;
    protected $graph;
    protected $rdfHelpers;
    protected $store;

    public function __construct(
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers,
        DataBlankHelper $dataBlankHelper,
        Store $store,
        NamedNode $graph
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->graph = $graph;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
    }

    /**
     * @param string $resourceUri
     */
    public function getRestrictionsForResource(string $resourceUri) : DataBlank
    {
        $blank = $this->dataBlankHelper->createDataBlank();
        $blank->initByStoreSearch($this->store, $this->graph, $resourceUri);

        /*
         * if its a proxy resource which inherits from another, get the properties of the other one.
         */
        if (null !== $blank->get('kno:inherits-all-properties-of')) {
            // get infos from the other resource
            $foreignResource = $blank->get('kno:inherits-all-properties-of');
            $foreignBlank = $this->dataBlankHelper->createDataBlank();
            $foreignBlank->initByStoreSearch($this->store, $this->graph, $foreignResource['_idUri']);
            // copy property-value combination into blank instance
            foreach ($foreignBlank as $property => $value) {
                // ignore internal fields
                if ('_idUri' == $property) {
                    continue;
                }
                $blank[$property] = $value;
            }
        }

        return $blank;
    }
}

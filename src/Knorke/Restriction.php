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
    protected $graph;
    protected $rdfHelpers;
    protected $store;

    public function __construct(
        Store $store,
        NamedNode $graph,
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->graph = $graph;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
    }

    /**
     * @param string $resourceUri
     */
    public function getRestrictionsForResource(string $resourceUri) : DataBlank
    {
        $blank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
        $blank->initBySetResult($this->store->query('SELECT * WHERE {<'. $resourceUri .'> ?p ?o.}'), $resourceUri);

        /*
         * if its a proxy resource which inherits from another, get the properties of the other one.
         */
        if (null !== $blank->get('kno:inherits-all-properties-of')) {
            // get infos from the other resource
            $foreignResource = $blank->get('kno:inherits-all-properties-of');
            $foreignBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers, array(
                'add_internal_data_fields' => false
            ));
            $foreignBlank->initByStoreSearch($this->store, $this->graph, $foreignResource);
            // copy property-value combination into blank instance
            foreach ($foreignBlank as $property => $value) {
                $blank[$property] = $value;
            }
        }

        return $blank;
    }
}

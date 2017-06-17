<?php

namespace Knorke;

use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\RdfHelpers;

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
        DataBlankHelper $dataBlankHelper
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->rdfHelpers = $rdfHelpers;
    }

    /**
     * @param string $resourceUri
     */
    public function getRestrictionsForResource(string $resourceUri) : DataBlank
    {
        $blank = $this->dataBlankHelper->load($resourceUri);

        /*
         * if its a proxy resource which inherits from another, get the properties of the other one.
         */
        if (isset($blank['kno:inherits-all-properties-of'])) {
            // get infos from the other resource
            $foreignBlank = $this->dataBlankHelper->load($blank['kno:inherits-all-properties-of']['_idUri']);
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

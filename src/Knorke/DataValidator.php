<?php

namespace Knorke\DataValidator;

use Knorke\DataBlank;
use Knorke\Data\ParserFactory;
use Knorke\Exception\DataValidatorException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\RdfHelpers;
use Saft\Store\Store;

/**
 *
 */
class DataValidator
{
    protected $commonNamespaces;
    protected $rdfHelpers;
    protected $store;

    public function __construct(
        CommonNamespaces $commonNamespaces,
        RdfHelpers $rdfHelpers,
        Store $store
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
    }

    /**
     * @param string $restrictionUri
     * @param mixed $restrictionValue
     * @param mixed $dataValueToCheck
     * @todo handle case for datatype=number: if value is true
     * @todo add datatype case for boolean
     */
    public function checkIfRestrictionIsApplied(
        string $restrictionUri,
        $restrictionValue,
        $dataValueToCheck
    ) {
        // make sure $restrictionUri is like kno:... and not http://...
        $restrictionUri = $this->commonNamespaces->shortenUri($restrictionUri);

        switch ($restrictionUri) {
            /*
             * check for data type
             */
            case 'kno:restrictionHasDatatype':
                // string
                if ('string' == $restrictionValue && is_string($dataValueToCheck)) {
                    return true;

                // real number, not in a string
                } elseif ('number' == $restrictionValue) {
                    if (ctype_digit((string)$dataValueToCheck)
                        && is_numeric($dataValueToCheck)
                        && false == is_string($dataValueToCheck)) {
                        return true;
                    }

                // boolean inside a string
                } elseif ('boolean-string' == $restrictionValue) {
                    // boolean in a string
                    if (is_string($dataValueToCheck) && in_array($dataValueToCheck, array('true', 'false'))) {
                        return true;
                    }
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' is not of datatype '. $restrictionValue);

                break;

            /*
             * check for minimum number
             */
            case 'kno:restrictionMinimumNumber':
                $restrictionValue = (float)$restrictionValue;
                if ($restrictionValue <= $dataValueToCheck) {
                    return true;
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' is lower than '. $restrictionValue);

                break;

            /*
             * check for maximum number
             */
            case 'kno:restrictionMaximumNumber':

                if ($restrictionValue >= $dataValueToCheck) {
                    return true;
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' is higher than '. $restrictionValue);

                break;

            /*
             * check for MATCHING regex
             */
            case 'kno:restrictionRegexMatch':

                $regex = $restrictionValue;
                if (0 < preg_match($regex, $dataValueToCheck)) {
                    return true;
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' doesnt match regex '. $restrictionValue);

                break;

            default:
                $e = new \DataValidatorException('Invalid restriction property used: '. $restrictionUri);
        }

        $e->setPayload($dataValueToCheck);
        throw $e;
    }

    /**
     * @param array $dataToCheck Array with the structure like: array('kno:Person/age' => 'foobar', ... )
     * @param string $typeUri URI of the class or resource which needs to be validated.
     * @return True if no errors were found, false if errors occoured or no data were given.
     * @throws \Exception in case of an validation error
     */
    public function validate(array $dataToCheck, string $typeUri)
    {
        if (0 == count($dataToCheck)) {
            return false;
        } elseif (false === $this->rdfHelpers->simpleCheckUri($typeUri)) {
            throw new DataValidatorException('Parameter $typeUri is not a valid URI: '. $typeUri);
        }

        // load resource behind given type
        $typedBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);

        $result = $this->store->query('SELECT * WHERE {<'. $typeUri .'> ?p ?o.}');
        $typedBlank->initBySetResult($result, $typeUri);

        // if only one hasProperty was given, transform it from string to array
        if (isset($typedBlank['kno:hasProperty']) && false == is_array($typedBlank['kno:hasProperty'])) {
            $typedBlank['kno:hasProperty'] = array($typedBlank['kno:hasProperty']);
        }

        // check each property of the type, if it is available and suits the restrictions
        foreach ($typedBlank['kno:hasProperty'] as $uri) {
            $fullObjectUri = $this->commonNamespaces->extendUri($uri);

            // load restrictions per property
            $propertyBlank = new DataBlank($this->commonNamespaces, $this->rdfHelpers);
            $propertyBlank->initBySetResult(
                $this->store->query('SELECT * WHERE {<'. $fullObjectUri .'> ?p ?o.}'),
                $fullObjectUri
            );

            foreach ($propertyBlank as $propertyUri => $object) {
                $propertyUri = $this->commonNamespaces->shortenUri($propertyUri);
                // if a property with a restriction was found
                if (false !== strpos($propertyUri, 'kno:restriction')) {
                    $this->checkIfRestrictionIsApplied(
                        $propertyUri,                           // e.g. kno:restrictionHasDatatype
                        $object,                                // e.g. string
                        $dataToCheck[$propertyBlank['_idUri']]  // e.g. "foobar"
                    );
                }
            }
        }

        // if the program runs until here, we know nothing bad happen and the data is valid
        return true;
    }
}

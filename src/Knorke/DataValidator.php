<?php

namespace Knorke;

use Knorke\DataBlank;
use Knorke\DataBlankHelper;
use Knorke\Data\ParserFactory;
use Knorke\Exception\DataValidatorException;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\NamedNode;
use Saft\Rdf\RdfHelpers;
use Saft\Store\Store;

/**
 *
 */
class DataValidator
{
    protected $commonNamespaces;
    protected $dataBlankHelper;
    protected $graphs;
    protected $rdfHelpers;
    protected $store;

    public function __construct(
        CommonNamespaces $commonNamespaces,
        DataBlankHelper $dataBlankHelper,
        RdfHelpers $rdfHelpers,
        Store $store,
        array $graphs
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->graphs = $graphs;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
    }

    /**
     * @param string $restrictionUri
     * @param mixed $restrictionValue
     * @param mixed $dataValueToCheck
     * @return bool
     * @throws DataValidatorException if a restriction doesn't apply.
     * @todo handle case for datatype=number: if value is true
     * @todo add datatype case for boolean
     */
    public function checkIfRestrictionIsApplied(
        string $restrictionUri,
        $restrictionValue,
        $dataValueToCheck
    ) : bool {
        // make sure $restrictionUri is like kno:... and not http://...
        $restrictionUri = $this->commonNamespaces->shortenUri($restrictionUri);

        switch ($restrictionUri) {
            /*
             * check for data type
             */
            case 'kno:restriction-has-datatype':
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
            case 'kno:restriction-minimum-number':
                $restrictionValue = (float)$restrictionValue;
                if ($restrictionValue <= $dataValueToCheck) {
                    return true;
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' is lower than '. $restrictionValue);

                break;

            /*
             * check for maximum number
             */
            case 'kno:restriction-maximum-number':

                if ($restrictionValue >= $dataValueToCheck) {
                    return true;
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' is higher than '. $restrictionValue);

                break;

            /*
             * check for MATCHING regex
             */
            case 'kno:restriction-regex-match':

                $regex = $restrictionValue;
                if (0 < preg_match($regex, $dataValueToCheck)) {
                    return true;
                }

                $e = new DataValidatorException('Data value '. $dataValueToCheck .' doesnt match regex '. $restrictionValue);

                break;

            default:
                $e = new DataValidatorException('Invalid restriction property used: '. $restrictionUri);
        }

        $e->setPayload($dataValueToCheck);
        throw $e;
    }

    /**
     * @param array $possibleKeys
     * @return mixed
     */
    protected function getArrayValue(array $array, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            if (isset($array[$key])) {
                return $array[$key];
            }
        }

        return null;
    }

    public function getAvailableRestrictions() : array
    {
        return $this->dataBlankHelper->find('kno:Restriction');
    }

    /**
     * @param array $dataToCheck Array with the structure like: array('kno:Person/age' => 'foobar', ... )
     * @return True if no errors were found
     * @throws \Exception in case of an validation error
     */
    public function validate(array $dataToCheck)
    {
        if (0 == count($dataToCheck)) {
            return false;
        }

        $availableRestrictions = $this->getAvailableRestrictions();

        // short and extended version of rdf:type
        $rdfTypeUriArray = array(
            $this->commonNamespaces->extendUri('rdf:type'),
            $this->commonNamespaces->shortenUri('rdf:type')
        );

        /*
         * check for rdf:type
         */
        $typeUri = $this->getArrayValue($dataToCheck, $rdfTypeUriArray);

        if (false === $this->rdfHelpers->simpleCheckUri($typeUri)) {
            throw new DataValidatorException('No type found. Did you miss key rdf:type?');
        }

        // load resource behind given type
        $type = $this->dataBlankHelper->load($typeUri);
        $typeArray = $type->getArrayCopy();

        // no has-property relations found, stop here, because there is nothing to check
        if (false == isset($typeArray['kno:has-property'])) {
            return true;
        }

        if (false == is_array($typeArray['kno:has-property'])) {
            $typeArray['kno:has-property'] = array($typeArray['kno:has-property']);
        }

        /*
          if object refereced by has-property looks like:
                 array(2) {
                  ["_idUri"]=>
                  string(19) "http://UserSettings"
                  ["kno:has-property"]=>
                  array(2) {
                    ["_idUri"]=>
                    string(24) "http://setting-startpage"
                    ["kno:restriction-has-datatype"]=>
                    string(6) "string"
                  }
                }

            put it into an array to reuse later loop:
         */
        if (isset($typeArray['kno:has-property']['_idUri'])) {
            $typeArray['kno:has-property'] = array($typeArray['kno:has-property']);
        }

        // check each property of the type, if it is available and suits the restrictions
        foreach ($typeArray['kno:has-property'] as $property) {
            $propertyUri = null;
            if (is_array($property)) {
                $propertyUri = $property['_idUri'];
            } elseif (is_string($property) && $this->rdfHelpers->simpleCheckUri($property)) {
                $propertyUri = $property;
            } else {
                throw new DataValidatorException(
                    'Property kno:has-property needs to point to an array (based on DataBlank) or string: '
                    . json_encode($property)
                );
            }

            /*
             * check for value of current property in $dataToCheck. both with extended and shorten URI.
             */
            if (isset($dataToCheck[$this->commonNamespaces->extendUri($propertyUri)])) {
                $value = $dataToCheck[$this->commonNamespaces->extendUri($propertyUri)];
            } elseif (isset($dataToCheck[$this->commonNamespaces->shortenUri($propertyUri)])) {
                $value = $dataToCheck[$this->commonNamespaces->shortenUri($propertyUri)];
            } else {
                throw new DataValidatorException(
                    'Property '. $propertyUri .' was not found in: '. json_encode($dataToCheck)
                );
            }

            // here you can assume $value is set.

            /*
             * recursive check sub structures
             */
            if (is_array($value)) {
                $property = $this->dataBlankHelper->load($propertyUri);

                // check type of referenced resource, if provided
                if (isset($property['kno:restriction-reference-is-of-type'])) {
                    $relatedToType = $property['kno:restriction-reference-is-of-type'];
                    $relatedToType = $this->dataBlankHelper->load($relatedToType['_idUri']);

                    // type not found
                    if (null == $this->getArrayValue($value, $rdfTypeUriArray)) {
                        throw new DataValidatorException(
                            'Property '. $propertyUri .' forces related instance be of type: '. $relatedToType
                            . ' but no rdf:type found at all.'
                        );
                    // required type differes from current one
                    } elseif ($relatedToType['_idUri'] !== $this->getArrayValue($value, $rdfTypeUriArray)) {
                        throw new DataValidatorException(
                            'Property '. $propertyUri .' forces related instance be of type: '. $relatedToType
                            . ' but found rdf:type '. $this->getArrayValue($value, $rdfTypeUriArray)
                        );
                    }
                }

                $this->validate($value);

            /*
             * assume no further sub structure, so directly check property
             */
            } else {
                // go through available restrictions and check, which one is set
                foreach ($availableRestrictions as $restriction) {
                    $shortenedRestrictionUri = $this->commonNamespaces->shortenUri($restriction['_idUri']);
                    if (isset($property[$shortenedRestrictionUri])) {
                        $this->checkIfRestrictionIsApplied(
                            $shortenedRestrictionUri,               // e.g. kno:restriction-has-datatype
                            $property[$shortenedRestrictionUri],    // e.g. string
                            $value                                  // e.g. "foobar"
                        );
                    }
                }
            }
        }

        // if the program runs until here, we know nothing bad happen and given data is valid

        return true;
    }
}

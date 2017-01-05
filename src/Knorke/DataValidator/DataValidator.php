<?php

namespace Knorke\DataValidator;

use Knorke\CommonNamespaces;
use Knorke\DataBlank;
use Knorke\Exception\DataValidatorException;
use Saft\Data\NQuadsParser;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIterator;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Rdf\NodeUtils;

/**
 *
 */
class DataValidator
{
    protected $commonNamespaces;

    public function __construct(CommonNamespaces $commonNamespaces)
    {
        $this->commonNamespaces = $commonNamespaces;
    }

    /**
     * @todo handle case for datatype=number: if value is true
     * @todo add datatype case for boolean
     */
    public function checkIfRestrictionIsApplied(
        $shortenPropertyUri,
        $shortRestrictionUri,
        $restrictionValue,
        array $dataToCheck
    ) {
        switch ($shortRestrictionUri) {
            case 'kno:restrictionHasDatatype':
                if ('string' == $restrictionValue && is_string($dataToCheck[$shortenPropertyUri])) {
                    return true;

                } elseif ('number' == $restrictionValue && ctype_digit((string)$dataToCheck[$shortenPropertyUri])) {
                    return true;
                }

                $e = new DataValidatorException('Property '. $shortenPropertyUri .' is not of datatype '. $restrictionValue);

                break;

            case 'kno:restrictionMinimumNumber':
                if ($restrictionValue <= $dataToCheck[$shortenPropertyUri]) {
                    return true;
                }

                $e = new DataValidatorException('Property '. $shortenPropertyUri .' is lower than '. $restrictionValue);

                break;

            case 'kno:restrictionMaximumNumber':

                if ($restrictionValue >= $dataToCheck[$shortenPropertyUri]) {
                    return true;
                }

                $e = new DataValidatorException('Property '. $shortenPropertyUri .' is higher than '. $restrictionValue);

                break;

            default:
                $e = new \DataValidatorException('Invalid restriction property used: '. $shortRestrictionUri);
        }

        $e->setPayload($dataToCheck[$shortenPropertyUri]);
        throw $e;
    }

    /**
     * @return StatementIterator
     * @todo use parser factory
     */
    public function loadOntologicalModel($filepath)
    {
        $parser = new NQuadsParser(
            new NodeFactoryImpl(new NodeUtils()),
            new StatementFactoryImpl(),
            new StatementIteratorFactoryImpl(),
            new NodeUtils()
        );
        return $parser->parseStringToIterator(file_get_contents($filepath));
    }

    /**
     * @param array $dataToCheck Array with the structure like: array('kno:Person/age' => 'foobar', ... )
     * @param StatementIterator $ontologicalModel
     * @param string $typeUri
     * @return True if no errors were found
     * @throws \Exception in case of an validation error
     */
    public function validate(array $dataToCheck, StatementIterator $ontologicalModel, $typeUri)
    {
        // load resource behind given type
        $typedBlank = new DataBlank($this->commonNamespaces);
        $typedBlank->initByStatementIterator($ontologicalModel, $typeUri);

        // check each property of the type, if it is available and suits the restrictions
        foreach ($typedBlank['kno:hasProperty'] as $shortendObjectUri) {
            // get full URI back (rdfs:label ===> http://....#label)
            $elements = explode(':', $shortendObjectUri);
            $fullObjectUri = $this->commonNamespaces->getUri($elements[0]) . $elements[1];

            // load restrictions per property
            $propertyBlank = new DataBlank($this->commonNamespaces);
            $propertyBlank->initByStatementIterator($ontologicalModel, $fullObjectUri);
            foreach ($propertyBlank as $shortenPropertyUri => $object) {
                // if a property with a restriction was found
                if (false !== strpos($shortenPropertyUri, 'kno:restriction')) {
                    $this->checkIfRestrictionIsApplied(
                        $propertyBlank['__subjectUri'], // e.g. kno-person:firstname
                        $shortenPropertyUri,            // e.g. kno:restrictionHasDatatype
                        $object,                        // e.g. string
                        $dataToCheck
                    );
                }
            }
        }

        // if the program runs until here, we know nothing bad happen and the data is valid
        return true;
    }
}

<?php

namespace Knorke;

use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\StatementFactoryImpl;

/**
 *
 */
class ClassHandler
{
    protected $domainFilepath;

    /**
     * @var Saft\Rdf\StatementIterator
     */
    protected $fileIterator;

    function __construct($domainFilepath)
    {
        $this->domainFilepath = $domainFilepath;

        $parser = new \Saft\Addition\EasyRdf\Data\ParserEasyRdf(
            new NodeFactoryImpl(),
            new StatementFactoryImpl(),
            'turtle'
        );

        $this->fileIterator = $parser->parseStreamToIterator('file://'. $domainFilepath);
    }

    /**
     * @param string $propertyUri
     */
    protected function getRestrictions($propertyUri)
    {
        $restrictionUris = $restrictions = array();

        // for a given property get related restriction URIs, if available
        foreach ($this->fileIterator as $statement) {
            if (
                $propertyUri == $statement->getSubject()->getUri()
                && 'http://localhost/k00ni/knorke/hasRestriction' == $statement->getPredicate()->getUri()
            ) {
                // assumption is, that only one or no restriction per class exists
                $restrictions = new \Saft\Rapid\Blank();
                $restrictions->initByStatementIterator($this->fileIterator, $statement->getObject()->getUri());
                return $restrictions;
            }
        }

        return null;
    }

    /**
     * Stores given data array. It assumes that the data were validated before.
     */
    public function save($data)
    {

    }

    public function validateData($data, $classUri)
    {
        $blankPerson = new \Saft\Rapid\Blank();
        $blankPerson->initByStatementIterator($this->fileIterator, $classUri);

        $shortenedPropertyUri = str_replace(
            'http://localhost/k00ni/knorke/',
            'knok:',
            $blankPerson['http://localhost/k00ni/knorke/hasProperty']
        );

        $restrictions = $this->getRestrictions($blankPerson['http://localhost/k00ni/knorke/hasProperty']);

        /**
         * checks minimum double value
         */
        if (isset($restrictions['http://localhost/k00ni/knorke/minimumDoubleValue'])) {
            $value = (double)$data[$shortenedPropertyUri];
            $minimumValue = $restrictions['http://localhost/k00ni/knorke/minimumDoubleValue'];

            if ($minimumValue > $value) {
                throw new \Exception('Value lower as '. $minimumValue .' : '. $value);
            }
        }

        /**
         * checks maximum double value
         */
        if (isset($restrictions['http://localhost/k00ni/knorke/maximumDoubleValue'])) {
            $value = (double)$data[$shortenedPropertyUri];
            $maximumValue = $restrictions['http://localhost/k00ni/knorke/maximumDoubleValue'];

            if ($maximumValue < $value) {
                throw new \Exception('Value higher as '. $maximumValue .' : '. $value);
            }
        }
    }
}

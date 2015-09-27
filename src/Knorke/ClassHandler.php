<?php

namespace Knorke;

use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\StatementFactoryImpl;

/**
 *
 */
class ClassHandler
{
    /**
     * @var string
     */
    protected $domainFilepath;

    /**
     * @var Saft\Rdf\StatementIterator
     */
    protected $fileIterator;

    /**
     * @var string
     */
    protected $mainUri = 'http://localhost/k00ni/knorke/';

    /**
     *
     */
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
                && $this->mainUri . 'hasRestriction' == $statement->getPredicate()->getUri()
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
        $blank = new \Saft\Rapid\Blank();
        $blank->initByStatementIterator($this->fileIterator, $classUri);

        $shortenedPropertyUri = str_replace($this->mainUri, 'knok:', $blank[$this->mainUri . 'hasProperty']);

        $restrictions = $this->getRestrictions($blank[$this->mainUri . 'hasProperty']);

        /*
         * Datatype: Double
         */

        /**
         * checks minimum double value
         */
        if (isset($restrictions[$this->mainUri . 'minimumDoubleValue'])) {
            $value = (double)$data[$shortenedPropertyUri];
            $minimumValue = $restrictions[$this->mainUri . 'minimumDoubleValue'];

            if ($minimumValue > $value) {
                throw new \Exception('Value lower as '. $minimumValue .' : '. $value);
            }
        }

        /**
         * checks maximum double value
         */
        if (isset($restrictions[$this->mainUri . 'maximumDoubleValue'])) {
            $value = (double)$data[$shortenedPropertyUri];
            $maximumValue = $restrictions[$this->mainUri . 'maximumDoubleValue'];

            if ($maximumValue < $value) {
                throw new \Exception('Value higher as '. $maximumValue .' : '. $value);
            }
        }

        /*
         * Datatype: String
         */

        /**
         * checks minimum string length
         */
        if (isset($restrictions[$this->mainUri . 'minimumStringLength'])) {
            $value = $data[$shortenedPropertyUri];
            $minimumStringLength = $restrictions[$this->mainUri . 'minimumStringLength'];

            if (strlen($value) < $minimumStringLength) {
                throw new \Exception('String length is lower as '. $minimumStringLength .' : '. strlen($value));
            }
        }

        /**
         * checks string against a regular expression
         */
        if (isset($restrictions[$this->mainUri . 'regexToApprove'])) {
            $value = $data[$shortenedPropertyUri];
            $regex = $restrictions[$this->mainUri . 'regexToApprove'];

            if (0 == preg_match($regex, $value)) {
                throw new \Exception('Regex '. $regex .' for the following string not match: '. $value);
            }
        }
    }
}

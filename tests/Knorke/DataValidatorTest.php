<?php

namespace Tests\Knorke;

use Knorke\DataValidator;
use Knorke\Exception\DataValidatorException;

class DataValidatorTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new DataValidator(
            $this->commonNamespaces,
            $this->rdfHelpers,
            $this->store
        );
    }

    /*
     * Tests for checkIfRestrictionIsApplied
     */

    public function testCheckIfRestrictionIsAppliedHasDatatypeBooleanString()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        $this->assertTrue($this->fixture->checkIfRestrictionIsApplied(
            $knoNs .'restriction-has-datatype', 'boolean-string', 'true'
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restriction-has-datatype', 'boolean-string', false
            );
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        } catch(DataValidatorException $e) {
            // good
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        }
    }

    public function testCheckIfRestrictionIsAppliedHasDatatypeNumber()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        $this->assertTrue($this->fixture->checkIfRestrictionIsApplied(
            $knoNs .'restriction-has-datatype', 'number', 11
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restriction-has-datatype', 'number', "11"
            );
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        } catch(DataValidatorException $e) {
            // good
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        }
    }

    public function testCheckIfRestrictionIsAppliedHasDatatypeString()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        $this->assertTrue($this->fixture->checkIfRestrictionIsApplied(
            $knoNs .'restriction-has-datatype', 'string', 'foobar'
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restriction-has-datatype', 'string', false
            );
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        } catch(DataValidatorException $e) {
            // good
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        }
    }

    public function testCheckIfRestrictionIsAppliedMinimumNumber()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        $this->assertTrue($this->fixture->checkIfRestrictionIsApplied(
            $knoNs .'restriction-minimum-number', 14, 15
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restriction-minimum-number', 1000, 0
            );
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        } catch(DataValidatorException $e) {
            // good
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        }
    }

    public function testCheckIfRestrictionIsAppliedMaximumNumber()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        $this->assertTrue($this->fixture->checkIfRestrictionIsApplied(
            $knoNs .'restriction-maximum-number', 1000, 15
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restriction-maximum-number', 0, 1000
            );
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        } catch(DataValidatorException $e) {
            // good
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        }
    }

    public function testCheckIfRestrictionIsAppliedRegex()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        $this->assertTrue($this->fixture->checkIfRestrictionIsApplied(
            $knoNs .'restriction-regex-match', '/\d/', '12'
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restriction-regex-match', '/\d/', 'aa'
            );
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        } catch(DataValidatorException $e) {
            // good
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // not good
            $this->fail('Expected an instance of Exception\DataValidatorException thrown.');
        }
    }

    /*
     * Tests for validate
     */

    public function testValidate()
    {
        $knoNs = $this->commonNamespaces->getUri('kno');

        /*
         * Add test data
         * http://Person/ kno:has-property :age .
         */
        $this->store->addStatements(array(
            /*
             * set has-property triples
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person/'),
                $this->nodeFactory->createNamedNode($knoNs . 'has-property'),
                $this->nodeFactory->createNamedNode('http://age/')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person/'),
                $this->nodeFactory->createNamedNode($knoNs . 'has-property'),
                $this->nodeFactory->createNamedNode('http://firstname/')
            ),
            /*
             * property details
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://age/'),
                $this->nodeFactory->createNamedNode($knoNs . 'restriction-has-datatype'),
                $this->nodeFactory->createLiteral('number')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://firstname/'),
                $this->nodeFactory->createNamedNode($knoNs . 'restriction-has-datatype'),
                $this->nodeFactory->createLiteral('string')
            ),
        ));

        $dataToValidate = array(
            'http://age/' => 15,
            'http://firstname/' => 'foobar'
        );

        $this->assertTrue($this->fixture->validate($dataToValidate, 'http://Person/'));
    }
}

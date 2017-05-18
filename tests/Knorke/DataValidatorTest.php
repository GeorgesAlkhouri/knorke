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
            $knoNs .'restrictionHasDatatype', 'boolean-string', 'true'
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restrictionHasDatatype', 'boolean-string', false
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
            $knoNs .'restrictionHasDatatype', 'number', 11
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restrictionHasDatatype', 'number', "11"
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
            $knoNs .'restrictionHasDatatype', 'string', 'foobar'
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restrictionHasDatatype', 'string', false
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
            $knoNs .'restrictionMinimumNumber', 14, 15
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restrictionMinimumNumber', 1000, 0
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
            $knoNs .'restrictionMaximumNumber', 1000, 15
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restrictionMaximumNumber', 0, 1000
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
            $knoNs .'restrictionRegexMatch', '/\d/', '12'
        ));

        try {
            $this->fixture->checkIfRestrictionIsApplied(
                $knoNs .'restrictionRegexMatch', '/\d/', 'aa'
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
         * http://Person/ kno:hasProperty :age .
         */
        $this->store->addStatements(array(
            /*
             * set hasProperty triples
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person/'),
                $this->nodeFactory->createNamedNode($knoNs . 'hasProperty'),
                $this->nodeFactory->createNamedNode('http://age/')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person/'),
                $this->nodeFactory->createNamedNode($knoNs . 'hasProperty'),
                $this->nodeFactory->createNamedNode('http://firstname/')
            ),
            /*
             * property details
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://age/'),
                $this->nodeFactory->createNamedNode($knoNs . 'restrictionHasDatatype'),
                $this->nodeFactory->createLiteral('number')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://firstname/'),
                $this->nodeFactory->createNamedNode($knoNs . 'restrictionHasDatatype'),
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
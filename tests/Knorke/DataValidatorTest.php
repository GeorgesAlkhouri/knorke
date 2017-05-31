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
            $this->dataBlankHelper,
            $this->rdfHelpers,
            $this->store,
            $this->testGraph
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
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
            Add test data

            http://Person/ kno:has-property http://age , http://firstname .

            http://age kno:restriction-has-datatype "number" .

            http://firstname kno:restriction-has-datatype "string" .

         */
        $this->store->addStatements(array(
            /*
             * set has-property triples
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://age')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://firstname')
            ),
            /*
             * property details
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://age'),
                $this->nodeFactory->createNamedNode('kno:restriction-has-datatype'),
                $this->nodeFactory->createLiteral('number')
            ),
        ));

        $dataToValidate = array(
            'rdf:type'          => 'http://Person',
            'http://age'        => 15,
            'http://firstname'  => 'foobar'
        );

        $this->assertTrue($this->fixture->validate($dataToValidate));
    }

    // test how it reacts if one of the has-property related properties are not in $dataToCheck
    public function testValidateNotAllPropertiesGiven()
    {
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
            Add test data

            http://Person/ kno:has-property http://age , http://firstname .

            http://age kno:restriction-has-datatype "number" .

            http://firstname kno:restriction-has-datatype "string" .

         */
        $this->store->addStatements(array(
            /*
             * set has-property triples
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://age')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://firstname')
            ),
            /*
             * property details
             */
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://age'),
                $this->nodeFactory->createNamedNode('kno:restriction-has-datatype'),
                $this->nodeFactory->createLiteral('number')
            ),
        ));

        $dataToValidate = array(
            'http://age' => 13,
            // firstname is missing
        );

        $this->expectException('Knorke\Exception\DataValidatorException');

        $this->assertFalse($this->fixture->validate($dataToValidate));
    }

    // test that exception is thrown, if no type info was found
    public function testValidateNoTypeInfoFound()
    {
        $this->expectException('Knorke\Exception\DataValidatorException');

        $this->fixture->validate(array(
            // rdf:type missing
            'http://foobar' => 'baz'
        ));
    }

    // test how it validates sub structures. it has to recursively checks
    public function testValidateSubStructures()
    {
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
         Add test data:

         http://Person kno:has-property http://has-user-settings .

         http://UserSettings kno:has-property http://setting-startpage .

         http://setting-startpage kno:restriction-has-datatype "string" .

         */
        $this->store->addStatements(array(
            // http://Person kno:has-property :has-user-settings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://has-user-settings')
            ),
            // http://UserSettings kno:has-property backmodel:setting-startpage .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://UserSettings'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://setting-startpage')
            ),
            // http://UserSettings kno:has-property http://number-of-xyze .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://UserSettings'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://number-of-xyz')
            ),
            // http://setting-startpage kno:restriction-has-datatype "string" .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://setting-startpage'),
                $this->nodeFactory->createNamedNode('kno:restriction-has-datatype'),
                $this->nodeFactory->createLiteral('string')
            ),
        ), $this->testGraph);

        $dataToValidate = array(
            // person level
            'rdf:type' => 'http://Person',
            'http://has-user-settings' => array(
                // sub level 1
                'rdf:type' => 'http://UserSettings',
                'http://setting-startpage' => '/index',
                'http://number-of-xyz' => 23
            )
        );

        $this->assertTrue($this->fixture->validate($dataToValidate));
    }

    // test that it cares about type info in restriction-reference-is-of-type
    public function testValidateSubStructuresExplicitTypeCheck()
    {
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
         Add test data:

         http://Person kno:has-property http://has-user-settings .
         http://has-user-settings kno:restriction-reference-is-of-type http://UserSettings .

         */
        $this->store->addStatements(array(
            // http://Person kno:has-property :has-user-settings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://has-user-settings')
            ),
            // http://has-user-settings kno:restriction-reference-is-of-type http://UserSettings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://has-user-settings'),
                $this->nodeFactory->createNamedNode('kno:restriction-reference-is-of-type'),
                $this->nodeFactory->createNamedNode('http://UserSettings')
            ),
        ), $this->testGraph);

        $dataToValidate = array(
            // person level
            'rdf:type' => 'http://Person',
            'http://has-user-settings' => array(
                // sub level 1
                'rdf:type' => 'http://UserSettings',
            )
        );

        $this->assertTrue($this->fixture->validate($dataToValidate));
    }

    // property wants a type, but no type given for sub entry
    public function testValidateSubStructuresExplicitTypeCheckNoTypeGiven()
    {
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
         Add test data:

         http://Person kno:has-property http://has-user-settings .
         http://has-user-settings kno:restriction-reference-is-of-type http://UserSettings .

         */
        $this->store->addStatements(array(
            // http://Person kno:has-property :has-user-settings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://has-user-settings')
            ),
            // http://has-user-settings kno:restriction-reference-is-of-type http://UserSettings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://has-user-settings'),
                $this->nodeFactory->createNamedNode('kno:restriction-reference-is-of-type'),
                $this->nodeFactory->createNamedNode('http://UserSettings')
            ),
        ), $this->testGraph);

        $dataToValidate = array(
            'rdf:type' => 'http://Person',
            'http://has-user-settings' => array(
                // type information rdf:type missing
            )
        );

        $this->expectException('Knorke\Exception\DataValidatorException');
        $this->fixture->validate($dataToValidate);
    }

    // property wants a type, but no type given for sub entry
    public function testValidateSubStructuresExplicitTypeCheckWrongTypeGiven()
    {
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
         Add test data:

         http://Person kno:has-property http://has-user-settings .
         http://has-user-settings kno:restriction-reference-is-of-type http://UserSettings .

         */
        $this->store->addStatements(array(
            // http://Person kno:has-property :has-user-settings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://has-user-settings')
            ),
            // http://has-user-settings kno:restriction-reference-is-of-type http://UserSettings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://has-user-settings'),
                $this->nodeFactory->createNamedNode('kno:restriction-reference-is-of-type'),
                $this->nodeFactory->createNamedNode('http://UserSettings')
            ),
        ), $this->testGraph);

        $dataToValidate = array(
            'rdf:type' => 'http://Person',
            'http://has-user-settings' => array(
                'rdf:type' => 'http://wrong-type'
                // wrong type given
            )
        );

        $this->expectException('Knorke\Exception\DataValidatorException');
        $this->fixture->validate($dataToValidate);
    }

    // test how it validates sub structures. it has to recursively checks
    public function testValidateSubStructuresCheckForFail()
    {
        // load knorke data
        $this->importer->importFile(__DIR__ .'/../../knowledge/knorke.nt', $this->testGraph);

        /*
         Add test data:

         http://Person kno:has-property http://has-user-settings .

         http://UserSettings kno:has-property http://setting-startpage .

         http://setting-startpage kno:restriction-has-datatype "string" .

         */
        $this->store->addStatements(array(
            // http://Person kno:has-property :has-user-settings .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://Person'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://has-user-settings')
            ),
            // http://UserSettings kno:has-property backmodel:setting-startpage .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://UserSettings'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://setting-startpage')
            ),
            // http://UserSettings kno:has-property http://number-of-xyze .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://UserSettings'),
                $this->nodeFactory->createNamedNode('kno:has-property'),
                $this->nodeFactory->createNamedNode('http://number-of-xyz')
            ),
            // http://setting-startpage kno:restriction-has-datatype "string" .
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://setting-startpage'),
                $this->nodeFactory->createNamedNode('kno:restriction-has-datatype'),
                $this->nodeFactory->createLiteral('string')
            ),
        ), $this->testGraph);

        $dataToValidate = array(
            // person level
            'rdf:type' => 'http://Person',
            'http://has-user-settings' => array(
                // sub level 1
                'rdf:type' => 'http://UserSettings',
                'http://setting-startpage' => '/index',
                // missing http://number-of-xyz
            )
        );

        $this->expectException('Knorke\Exception\DataValidatorException');

        $this->assertTrue($this->fixture->validate($dataToValidate));
    }
}

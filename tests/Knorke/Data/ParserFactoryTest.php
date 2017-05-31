<?php

namespace Tests\Knorke\Data;

use Knorke\Data\ParserFactory;
use Saft\Data\Parser;
use Tests\Knorke\UnitTestCase;

class ParserFactoryTest extends UnitTestCase
{
    /**
     * This list represents all serializations that are supported by the Parsers behind the ParserFactory
     * class to test.
     *
     * @var array
     */
    protected $availableSerializations = array('n-triples', 'n-quads', 'rdf-xml', 'turtle');

    public function setUp()
    {
        parent::setUp();

        $this->fixture = new ParserFactory(
            $this->nodeFactory,
            $this->statementFactory,
            $this->statementIteratorFactory,
            $this->rdfHelpers
        );
    }

    /*
     * Tests for createParserFor
     */

    // simple test to go through all availableSerializations and check for each that an object
    // is returned by the ParserFactory instance.
    public function testCreateParserFor()
    {
        if (0 == count($this->availableSerializations)) {
            $this->markTestSkipped('Array $availableSerializations contains no entries.');
        }
        foreach ($this->availableSerializations as $serialization) {
            $parser = $this->fixture->createParserFor($serialization);
            $this->assertTrue(is_object($parser));
            $this->assertTrue($parser instanceof Parser);
        }
    }

    public function testCreateParserForRequestInvalidSerialization()
    {
        $this->assertNull($this->fixture->createParserFor('invalid serialization'));
    }
}

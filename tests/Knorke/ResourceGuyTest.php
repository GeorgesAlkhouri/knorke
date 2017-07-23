<?php

namespace Tests\Knorke;

use Knorke\ResourceGuy;

class ResourceGuyTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new ResourceGuy($this->commonNamespaces);
    }

    /*
     * Tests for isset
     */

    public function testIsset()
    {
        $this->fixture['foaf:name'] = $this->nodeFactory->createLiteral('Mister X');

        $this->assertTrue(isset($this->fixture[$this->commonNamespaces->getUri('foaf') . 'name']));
    }

    /*
     * Tests for getArrayCopy
     */

    public function testGetArrayCopy()
    {
        $this->fixture->reset();

        $this->fixture['foaf:knows'] = new ResourceGuy($this->commonNamespaces);
        $this->fixture['foaf:knows']['_idUri'] = $this->nodeFactory->createNamedNode('http://another-guy/');

        $this->fixture['foaf:name'] = $this->nodeFactory->createLiteral('Mister X');

        $this->assertEquals(
            array(
                'foaf:knows' => array(
                    '_idUri' => 'http://another-guy/'
                ),
                'foaf:name' => 'Mister X'
            ),
            $this->fixture->getArrayCopy()
        );
    }

    /*
     * Tests for get and set
     */

    public function testGetNothingFound()
    {
        $this->assertNull($this->fixture['not_set_entry']);
    }

    public function testSetAndGet()
    {
        $this->fixture->reset();

        // prefixed to extended
        $this->fixture['foaf:name'] = $this->nodeFactory->createLiteral('Mister X');
        $this->assertEquals('Mister X', $this->fixture[$this->commonNamespaces->getUri('foaf') . 'name']->getValue());

        $this->fixture->reset();

        // extended to prefixed
        $this->fixture[$this->commonNamespaces->getUri('foaf') . 'name'] = $this->nodeFactory->createLiteral('Mister X');
        $this->assertEquals('Mister X', $this->fixture['foaf:name']->getValue());

        $this->fixture->reset();

        $this->fixture['foaf:knows'] = $this->nodeFactory->createNamedNode('http://foaf/Person/MisterX');
        $this->assertEquals('http://foaf/Person/MisterX', $this->fixture['foaf:knows']->getUri());
    }

    public function testSetUsingAString()
    {
        $this->expectException('Knorke\Exception\KnorkeException');
        $this->fixture['foaf:name'] = 'Stupid';
    }
}

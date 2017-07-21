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
     * Tests for get and set
     */

    public function testGetNothingFound()
    {
        $this->assertNull($this->fixture['not_set_entry']);
    }

    public function testSetAndGet()
    {
        $this->fixture['foaf:name'] = $this->nodeFactory->createLiteral('Mister X');
        $this->assertEquals('Mister X', $this->fixture['foaf:name']->getValue());
        $this->assertEquals('Mister X', $this->fixture[$this->commonNamespaces->getUri('foaf') . 'name']->getValue());

        $this->fixture['foaf:knows'] = $this->nodeFactory->createNamedNode('http://foaf/Person/MisterX');
        $this->assertEquals('http://foaf/Person/MisterX', $this->fixture['foaf:knows']->getUri());
    }

    public function testSetUsingAString()
    {
        $this->expectException('Knorke\Exception\KnorkeException');
        $this->fixture['foaf:name'] = 'Stupid';
    }
}

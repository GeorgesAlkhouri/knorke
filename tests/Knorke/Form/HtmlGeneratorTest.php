<?php

namespace Tests\Knorke\Form;

use Knorke\Form\HtmlGenerator;
use Tests\Knorke\UnitTestCase;

class HtmlGeneratorTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new HtmlGenerator();
    }

    /*
     * Tests for transformFormArrayToCoolHtml
     */

    public function testTransformFormArrayToCoolHtml()
    {
        $formArray = array(
            '<form>',
                '<input type=""/>',
                '<input type=""/>',
                '<div>',
                    'cool here!',
                '</div>',
            '</form>',
        );

        $this->assertEquals(
            '
<form>
    <input type=""/>
    <input type=""/>
    <div>
        cool here!
    </div>
</form>',
            $this->fixture->transformFormArrayToCoolHtml($formArray)
        );
    }

    public function testTransformFormArrayToCoolHtmlNon0Level()
    {
        $formArray = array(
            '<form>',
                '<input type=""/>',
                '<input type=""/>',
                '<div>',
                    'cool here!',
                '</div>',
            '</form>',
        );

        $this->assertEquals(
            '
    <form>
        <input type=""/>
        <input type=""/>
        <div>
            cool here!
        </div>
    </form>',
            $this->fixture->transformFormArrayToCoolHtml($formArray, 1)
        );
    }
}

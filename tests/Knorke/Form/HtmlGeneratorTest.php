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
                '<select>',
                    '<option>1</option>',
                '</select>',
                '<span>',
                    'some stuff',
                '</span>',
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
    <select>
        <option>1</option>
    </select>
    <span>
        some stuff
    </span>
</form>',
            $this->fixture->transformFormArrayToCoolHtml($formArray)
        );
    }

    // expect exception because of different levels at the end
    public function testTransformFormArrayToCoolHtmlExceptException()
    {
        $formArray = array(
            '<form>',
                '<input type=""/>',
            // </form> is missing here
        );

        // expect exception because </form> is missing and therefore different levels
        $this->expectException('Knorke\Exception\KnorkeException');

        $this->fixture->transformFormArrayToCoolHtml($formArray);
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

    public function testTransformFormArrayToCoolHtmlRegression1()
    {
        $formArray = array(
            '<div id="form:has-x__entry_1">',
            '<br/><br/>',
            '<label for="form_X__rdfs_label__1">Title</label>',
            '<input type="text" id="form_X__rdfs_label__1"name="form:X__rdfs:label__1"value="..." required="required">',
            '<br/><br/>',
            '<input type="text" id="form_X__form_comment__1" name="foo" value="..." required="required">',
            '</div>'
        );

        $this->assertEquals(
            '
<div id="form:has-x__entry_1">
    <br/><br/>
    <label for="form_X__rdfs_label__1">Title</label>
    <input type="text" id="form_X__rdfs_label__1"name="form:X__rdfs:label__1"value="..." required="required">
    <br/><br/>
    <input type="text" id="form_X__form_comment__1" name="foo" value="..." required="required">
</div>',
            $this->fixture->transformFormArrayToCoolHtml($formArray, 0)
        );
    }
}

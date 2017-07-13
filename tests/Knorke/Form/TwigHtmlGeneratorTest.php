<?php

namespace Tests\Knorke\Form;

use Knorke\Form\TwigHtmlGenerator;
use Tests\Knorke\UnitTestCase;

class TwigHtmlGeneratorTest extends HtmlGeneratorTest
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new TwigHtmlGenerator();
    }

    /*
     * Tests for transformFormArrayToCoolHtml
     */

    public function testTransformFormArrayToCoolHtml1()
    {
        $formArray = array(
            '<form>',
                '<input type=""/>',
                '<input type=""/>',
                '<div>',
                    '{% if 1==2 %}',
                        'cool twig',
                    '{% endif %}',
                '</div>',
            '</form>',
        );

        $this->assertEquals(
            '

<form>

    <input type=""/>

    <input type=""/>

    <div>

        {% if 1==2 %}

            cool twig

        {% endif %}

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
                    '{% if 1==2 %}',
                        'cool twig',
                    '{% endif %}',
                '</div>',
            '</form>',
        );

        $this->assertEquals(
            '

    <form>

        <input type=""/>

        <input type=""/>

        <div>

            {% if 1==2 %}

                cool twig

            {% endif %}

        </div>

    </form>',
            $this->fixture->transformFormArrayToCoolHtml($formArray, 1)
        );
    }

    // Regression1 already defined on HtmlGeneratorTest

    public function testTransformFormArrayToCoolHtmlRegression2()
    {
        $formArray = array(
            '<form method="post" action="{% if root_item["_idUri"] is defined %}{{ root_item["_idUri"] }}{% endif %}">',
            '<input type="hidden" name="__type" value="http://bar">',
            '{% if root_item["_idUri"] is defined %}',
            '<input type="hidden" name="__idUri" value="{{ root_item["_idUri"] }}">',
            '{% else %}',
            '<input type="hidden" name="__uriSchema" value="">',
            '{% endif %}',
            '<br/><br/>',
            '</form>',
        );

        $this->assertEquals(
            '

<form method="post" action="{% if root_item["_idUri"] is defined %}{{ root_item["_idUri"] }}{% endif %}">

    <input type="hidden" name="__type" value="http://bar">

    {% if root_item["_idUri"] is defined %}

        <input type="hidden" name="__idUri" value="{{ root_item["_idUri"] }}">

        {% else %}

        <input type="hidden" name="__uriSchema" value="">

    {% endif %}

    <br/><br/>

</form>',
            $this->fixture->transformFormArrayToCoolHtml($formArray, 0)
        );
    }
}

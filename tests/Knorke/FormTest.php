<?php

namespace Tests\Knorke;

use Knorke\Form;

class FormTest extends UnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new Form(
            $this->store,
            $this->dataBlankHelper,
            $this->rdfHelpers,
            $this->commonNamespaces,
            $this->nodeFactory,
            $this->statementFactory,
            array(
                'form' => array(
                    'action_url' => 'http://url/',
                )
            )
        );
    }

    /*
     * Tests for buildSubElementUriByUriSchema
     */

    public function testBuildSubElementUriByUriSchema()
    {
        $propertyBlank = $this->dataBlankHelper->createDataBlank();

        $this->assertEquals(
            'http://foo/label/',
            $this->fixture->buildSubElementUriByUriSchema(
                'http://foo/?foo:label?/',
                array( // = raw form input
                    'foo:root-element__foo:root-prop-1__foo:sub-element-type__foo:label__0' => 'label'
                ),
                'foo:root-element',
                'foo:root-prop-1',
                'foo:sub-element-type',
                '0',
                'http://root-uri/'
            )
        );
    }

    public function testBuildSubElementUriByUriSchemaWithRootUri()
    {
        $propertyBlank = $this->dataBlankHelper->createDataBlank();

        $this->assertEquals(
            'http://root-uri/label',
            $this->fixture->buildSubElementUriByUriSchema(
                '%root-uri%?foo:label?',
                array( // = raw form input
                    'foo:root-element__foo:root-prop-1__foo:sub-element-type__foo:label__0' => 'label'
                ),
                'foo:root-element',
                'foo:root-prop-1',
                'foo:sub-element-type',
                '0',
                'http://root-uri/'
            )
        );
    }

    /*
     * Tests for getSubFormElements
     */

    public function testGetSubFormElements()
    {
        $html = '%subForm rootElementUri="form:type1" rootElementPropertyUri="form:t1-p2" subElementTypeUri="form:type2" %';

        $res = $this->fixture->getSubFormElements($html);

        $this->assertEquals(
            array('form:type1', 'form:t1-p2', 'form:type2'),
            $res
        );
    }

    /*
     * Tests for generateFormFor
     */

    public function testGenerateFormFor()
    {
        // add test data
        $this->importTurtle('
            @prefix form: <http://form/> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdfs: <'. $this->commonNamespaces->getUri('rdfs') .'> .

            form:type1 ;
                kno:has-property form:t1-p1 ;
                kno:has-property form:t1-p2 .

            form:t1-p1
                rdfs:label "Root-Prop-1 DE"@de ;
                rdfs:label "Root-Prop-1 EN"@en .

            form:t1-p2
                kno:restriction-reference-is-of-type form:type2 .

            form:type2
                kno:has-property form:t2-p1 ;
                kno:has-property form:t2-p2 .

            form:t2-p1
                rdfs:label "Type2-Prop-1"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        $this->assertEquals(
            array('

<form method="post" action="http://url/">

    <input type="hidden" name="__type" value="form:type1">

    {% if root_item["_idUri"] is defined %}

        <input type="hidden" name="__idUri" value="{{ root_item["_idUri"] }}">

        {% else %}

        <input type="hidden" name="__uriSchema" value="">

    {% endif %}

    <br/><br/>

    <input type="text" name="form:t1-p1" value="{% if root_item["form:t1-p1"] is defined %}{{ root_item["form:t1-p1"] }}{% endif %}">

    <div id="form_t1_p2__container">

        <input type="hidden" name="form:t1-p2__type" value="form:type2">

        <input type="hidden" name="form:t1-p2__uriSchema" value="">

        {% set entry_count = 0 %}

        {% if root_item["form:t1-p2"] is defined %}

            {% for key,sub_item in root_item["form:t1-p2"] %}

                <div class="form_t1_p2_element">

                    <input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p1__{{ entry_count }}" value="{{ sub_item["form:t2-p1"] }}">

                    <input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p2__{{ entry_count }}" value="{{ sub_item["form:t2-p2"] }}">

                </div>

            {% endfor %}

            {% set entry_count = entry_count+1 %}

        {% endif %}

        <button class="btn btn-primary" id="form_t1_p2__btn" type="button">Add</button>

        <input type="hidden" name="form:type1__form:t1-p2__number" value="{{ 1+root_item["form:t1-p2"]|length }}" id="form_t1_p2__number">

    </div>

</form>',
                /*
                 * JS
                 */
                array(
                    '
<script type="text/javascript">

    // store latest number of root_item["form:t1-p2"] entries
    var form_t1_p2__number = {{ 1+root_item["form:t1-p2"]|length }};
    $(document).ready(function(){
        /*
         * dynamically add further fields to #form_t1_p2__container
         */
        $("#form_t1_p2__btn").on("click", function(){
            ++form_t1_p2__number;

            $("#form_t1_p2__container").append(
                `<br/>


                <div class="form_t1_p2_element">

                    <input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p1__` + backmodel_has_areas__number + `" value="">

                    <input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p2__` + backmodel_has_areas__number + `" value="">

                </div>`
            );

            $("#form_t1_p2__number").val(form_t1_p2__number);
        });
    });
</script>'
                )
            ),
            $this->fixture->generateFormFor('form:type1')
        );
    }

    /*
     * Tests for generateSubForm
     */

    public function testGenerateSubForm()
    {
        // add test data
        $this->importTurtle('
            @prefix form: <http://form/> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdfs: <'. $this->commonNamespaces->getUri('rdfs') .'> .

            form:type1 ;
                kno:has-property form:t1-p1 ;
                kno:has-property form:t1-p2 .

            form:t1-p1
                rdfs:label "Root-Prop-1 DE"@de ;
                rdfs:label "Root-Prop-1 EN"@en .

            form:t1-p2
                kno:restriction-reference-is-of-type form:type2 .

            form:type2
                kno:has-property form:t2-p1 ;
                kno:has-property form:t2-p2 .

            form:t2-p1
                rdfs:label "Type2-Prop-1"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        $this->assertEquals(
            array(
                array(
                    '<div id="form_t1_p2__container">',
                        '<input type="hidden" name="form:t1-p2__type" value="form:type2">',
                        '<input type="hidden" name="form:t1-p2__uriSchema" value="">',
                        '{% set entry_count = 0 %}',
                        '{% if root_item["form:t1-p2"] is defined %}',
                            '{% for key,sub_item in root_item["form:t1-p2"] %}',
                                '<div class="form_t1_p2_element">',
                                    '<input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p1__{{ entry_count }}" value="{{ sub_item["form:t2-p1"] }}">',
                                    '<input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p2__{{ entry_count }}" value="{{ sub_item["form:t2-p2"] }}">',
                                '</div>',
                            '{% endfor %}',
                            '{% set entry_count = entry_count+1 %}',
                        '{% endif %}',
                        '<button class="btn btn-primary" id="form_t1_p2__btn" type="button">Add</button>',
                        '<input type="hidden" name="form:type1__form:t1-p2__number" value="{{ 1+root_item["form:t1-p2"]|length }}" id="form_t1_p2__number">',
                    '</div>',
                ),
'
<script type="text/javascript">

    // store latest number of root_item["form:t1-p2"] entries
    var form_t1_p2__number = {{ 1+root_item["form:t1-p2"]|length }};
    $(document).ready(function(){
        /*
         * dynamically add further fields to #form_t1_p2__container
         */
        $("#form_t1_p2__btn").on("click", function(){
            ++form_t1_p2__number;

            $("#form_t1_p2__container").append(
                `<br/>


                <div class="form_t1_p2_element">

                    <input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p1__` + backmodel_has_areas__number + `" value="">

                    <input type="text" name="form:type1__form:t1-p2__form:type2__form:t2-p2__` + backmodel_has_areas__number + `" value="">

                </div>`
            );

            $("#form_t1_p2__number").val(form_t1_p2__number);
        });
    });
</script>'
            ),
            $this->fixture->generateSubForm('form:type1', 'form:t1-p2', 'form:type2')
        );
    }

    /*
     * Tests for inputFieldHidden
     */

    public function testInputFieldHidden()
    {
        $this->assertEquals(
            '<input type="hidden" name="name" value="val" id="id">',
            $this->fixture->inputFieldHidden('name', 'val', 'id')
        );
        $this->assertEquals(
            '<input type="hidden" name="name" value="val">',
            $this->fixture->inputFieldHidden('name', 'val')
        );
        $this->assertEquals(
            '<input type="hidden" name="name" value="">',
            $this->fixture->inputFieldHidden('name')
        );
    }

    /*
     * Tests for inputFieldText
     */

    public function testInputFieldText()
    {
        $this->assertEquals(
            '<input type="text" name="name" value="val" id="id" required="required">',
            $this->fixture->inputFieldText('name', 'val', 'id', true)
        );

        $this->assertEquals(
            '<input type="text" name="name" value="val" id="id">',
            $this->fixture->inputFieldText('name', 'val', 'id')
        );
        $this->assertEquals(
            '<input type="text" name="name" value="val">',
            $this->fixture->inputFieldText('name', 'val')
        );
    }

    /*
     * Tests for transformParameterArrayToStatementArray
     */

    // tests update of existing entries
    public function testTransformParameterArrayToStatementArray()
    {
        // add test data
        $this->importTurtle('
            @prefix form: <http://form/> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdfs: <'. $this->commonNamespaces->getUri('rdfs') .'> .

            form:type1 ;
                kno:has-property form:t1-p1 ;
                kno:has-property form:t1-p2 ;
                kno:has-property form:t1-p3 .

            form:t1-p1
                rdfs:label "Root-Prop-1 DE"@de ;
                rdfs:label "Root-Prop-1 EN"@en .

            form:t1-p2
                kno:restriction-reference-is-of-type form:type2 .

            form:type2
                kno:has-property form:t2-p1 ;
                kno:has-property form:t2-p2 .

            form:t2-p1
                rdfs:label "Type2-Prop-1"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        /*
         * set parameters for function to test
         */
        $typeUri = 'form:type1';
        $formInput = array(
            '__type'                                                     => $typeUri,
            '__idUri'                                                    => 'http://type1/',
            'form:t1-p1'                                                 => 't1-p1-value',
            // sub entry 1
            'form:type1__form:t1-p2__form:type2__uriSchema'              => '%root-uri%?form:t2-p1?',
            'form:type1__form:t1-p2__form:type2____idUri__0'             => 'http://type1/sub-value1',
            'form:type1__form:t1-p2__form:type2__form:t2-p1__0'          => 'sub-value1',
            'form:type1__form:t1-p2__form:type2__form:t2-p2__0'          => 'sub-value2',
            // sub entry 2 (not yet "created", therefore needs an URI)
            'form:type1__form:t1-p2__form:type2__form:t2-p1__1'          => 'sub-value3',
            'form:type1__form:t1-p2__form:type2__form:t2-p2__1'          => 'sub-value4',
            'form:type1__form:t1-p2__number'                             => '2',

            'form:t1-p3'                                                 => 't1-p3-value',
        );

        $res = $this->fixture->transformParameterArrayToStatementArray($formInput);

        $this->assertEquals(
            array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/'),
                    $this->nodeFactory->createNamedNode('form:t1-p1'),
                    $this->nodeFactory->createLiteral('t1-p1-value')
                ),
                // root element ===> sub element 1
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/'),
                    $this->nodeFactory->createNamedNode('form:t1-p2'),
                    $this->nodeFactory->createNamedNode('http://type1/sub-value1')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/sub-value1'),
                    $this->nodeFactory->createNamedNode('form:t2-p1'),
                    $this->nodeFactory->createLiteral('sub-value1')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/sub-value1'),
                    $this->nodeFactory->createNamedNode('form:t2-p2'),
                    $this->nodeFactory->createLiteral('sub-value2')
                ),
                // root element ===> sub element 2
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/'),
                    $this->nodeFactory->createNamedNode('form:t1-p2'),
                    $this->nodeFactory->createNamedNode('http://type1/sub_value3')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/sub_value3'),
                    $this->nodeFactory->createNamedNode('form:t2-p1'),
                    $this->nodeFactory->createLiteral('sub-value3')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/sub_value3'),
                    $this->nodeFactory->createNamedNode('form:t2-p2'),
                    $this->nodeFactory->createLiteral('sub-value4')
                ),
                // t1-p3
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://type1/'),
                    $this->nodeFactory->createNamedNode('form:t1-p3'),
                    $this->nodeFactory->createLiteral('t1-p3-value')
                )
            ),
            $res
        );
    }

    // tests for new root entity
    public function testTransformParameterArrayToStatementArrayNewRootEntity()
    {
        // add test data
        $this->importTurtle('
            @prefix form: <http://form/> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .
            @prefix rdfs: <'. $this->commonNamespaces->getUri('rdfs') .'> .

            form:type1 ;
                kno:has-property form:t1-p1 ;
                kno:has-property form:t1-p2 ;
                kno:has-property form:t1-p3 .

            form:t1-p1
                rdfs:label "Root-Prop-1 DE"@de ;
                rdfs:label "Root-Prop-1 EN"@en .

            form:t1-p2
                kno:restriction-reference-is-of-type form:type2 .

            form:type2
                kno:has-property form:t2-p1 ;
                kno:has-property form:t2-p2 .

            form:t2-p1
                rdfs:label "Type2-Prop-1"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        /*
         * set parameters for function to test
         */
        $typeUri = 'form:type1';
        $formInput = array(
            '__type'                                                     => $typeUri,
            '__uriSchema'                                                => 'http://new/?form:t1-p1?/',
            'form:t1-p1'                                                 => 't1-p1-value',
            // sub entry 1
            'form:type1__form:t1-p2__form:type2__uriSchema'              => '%root-uri%?form:t2-p1?',
            'form:type1__form:t1-p2__form:type2__form:t2-p1__0'          => 'sub-value1',
            'form:type1__form:t1-p2__form:type2__form:t2-p2__0'          => 'sub-value2',
            // sub entry 2 (not yet "created", therefore needs an URI)
            'form:type1__form:t1-p2__form:type2__form:t2-p1__1'          => 'sub-value3',
            'form:type1__form:t1-p2__form:type2__form:t2-p2__1'          => 'sub-value4',
            'form:type1__form:t1-p2__number'                             => '2',

            'form:t1-p3'                                                 => 't1-p3-value',
        );

        $res = $this->fixture->transformParameterArrayToStatementArray($formInput);

        $this->assertEquals(
            array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/'),
                    $this->nodeFactory->createNamedNode('form:t1-p1'),
                    $this->nodeFactory->createLiteral('t1-p1-value')
                ),
                // root element ===> sub element 1
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/'),
                    $this->nodeFactory->createNamedNode('form:t1-p2'),
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/sub_value1')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/sub_value1'),
                    $this->nodeFactory->createNamedNode('form:t2-p1'),
                    $this->nodeFactory->createLiteral('sub-value1')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/sub_value1'),
                    $this->nodeFactory->createNamedNode('form:t2-p2'),
                    $this->nodeFactory->createLiteral('sub-value2')
                ),
                // root element ===> sub element 2
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/'),
                    $this->nodeFactory->createNamedNode('form:t1-p2'),
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/sub_value3')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/sub_value3'),
                    $this->nodeFactory->createNamedNode('form:t2-p1'),
                    $this->nodeFactory->createLiteral('sub-value3')
                ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/sub_value3'),
                    $this->nodeFactory->createNamedNode('form:t2-p2'),
                    $this->nodeFactory->createLiteral('sub-value4')
                ),
                // t1-p3
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://new/t1_p1_value/'),
                    $this->nodeFactory->createNamedNode('form:t1-p3'),
                    $this->nodeFactory->createLiteral('t1-p3-value')
                )
            ),
            $res
        );
    }
}

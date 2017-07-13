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
            array(
                'form' => array(
                    'action_url' => 'http://url/',
                )
            )
        );
    }

    /*
     * Tests for buildUriByUriSchema
     */

    public function testBuildUriByUriSchema()
    {
        $propertyBlank = $this->dataBlankHelper->createDataBlank();

        $this->assertEquals(
            'http://foo/was_geht/',
            $this->fixture->buildUriByUriSchema(
                'http://foo/?foo:label?/',
                $propertyBlank,
                array( // = raw form input
                    'foo:label' => 'was-geht'
                )
            )
        );
    }

    public function testBuildUriByUriSchemaWithRootUri()
    {
        $propertyBlank = $this->dataBlankHelper->createDataBlank();

        $this->assertEquals(
            'http://bar/was_geht/',
            $this->fixture->buildUriByUriSchema(
                '%root-uri%?foo:label?/',
                $propertyBlank,
                array( // = raw form input
                    'foo:label' => 'was-geht'
                ),
                'http://bar/'
            )
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

            form:Event ;
                kno:has-property form:located-in ;
                kno:has-property form:has-x .

            form:has-x
                kno:restriction-reference-is-of-type form:X .

            form:located-in
                rdfs:label "Findet statt in"@de ;
                rdfs:label "Located in"@en .

            form:X
                kno:has-property rdfs:label ;
                kno:has-property form:comment .

            rdfs:label
                rdfs:label "Title"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        $this->assertEquals(
'
<form method="post" action="http://url/">
    <input type="hidden" name="__type" value="form:Event">
    {% if root_item["_idUri"] is defined %}
        <input type="hidden" name="__idUri" value="{{ root_item["_idUri"] }}">
        {% else %}
        <input type="hidden" name="__uriSchema" value="">
    {% endif %}
    <br/><br/>
    <label for="form_located_in">Findet statt in</label>
    <input type="text" id="form_located_in" name="form:located-in" value="{% if root_item["form:located-in"] is defined %}{{ root_item["form:located-in"] }}{% endif %}" required="required">
    <div id="form_has_x__container">
        <input type="hidden" name="form:has-x__type" value="form:X">
        <input type="hidden" name="form:has-x__uriSchema" value="">
        {% set entry_count = 0 %}
        {% if root_item["form:has-x"] is defined %}
            {% for key,sub_item in root_item["form:has-x"] %}
                <div id="form:has-x__entry_{{key}}">
                    <input type="hidden" name="form:X____idUri__{{key}}" value="{{ sub_item["_idUri"] }}">
                    <label for="form_X__rdfs_label__{{key}}">Title</label>
                    <input type="text" id="form_X__rdfs_label__{{key}}" name="form:X__rdfs:label__{{key}}" value="{{ sub_item["rdfs:label"] }}" required="required">
                    <input type="text" id="form_X__form_comment__{{key}}" name="form:X__form:comment__{{key}}" value="{{ sub_item["form:comment"] }}" required="required">
                </div>
                {% set entry_count = key %}
            {% endfor %}
            {% set entry_count = entry_count+1 %}
        {% endif %}
        <div id="form_has_x__entry_{{ entry_count }}">
            <br/><br/>
            <label for="form_X__rdfs_label__{{ entry_count }}">Title</label>
            <input type="text" id="form_X__rdfs_label__{{ entry_count }}" name="form:X__rdfs:label__{{ entry_count }}" value="{% if sub_item["rdfs:label"] is defined %}{{ sub_item["rdfs:label"] }}{% endif %}" required="required">
            <br/><br/>
            <input type="text" id="form_X__form_comment__{{ entry_count }}" name="form:X__form:comment__{{ entry_count }}" value="{% if sub_item["form:comment"] is defined %}{{ sub_item["form:comment"] }}{% endif %}" required="required">
        </div>
    </div>
    {% if root_item["form:has-x"] is defined %}
        <input type="hidden" id="form_has_x__number" name="form:has-x__number" value="{{ 1+root_item["form:has-x"]|length }}"/>
        {% else %}
        <input type="hidden" id="form_has_x__number" name="form:has-x__number" value="1"/>
    {% endif %}
    <button class="btn btn-primary" id="form_has_x__btn" type="button">Add</button>
    <br/><br/>
    <button class="btn btn-primary" type="submit">Save</button>
    {% if root_item["_idUri"] is defined %}
        <input type="hidden" name="action" value="update">
        {% else %}
        <input type="hidden" name="action" value="create">
    {% endif %}
</form>


<script type="text/javascript">

    // store latest number of root_item["form:has-x"] entries
    var form_has_x__number = {{ 1+root_item["form:has-x"]|length }};
    $(document).ready(function(){
        /*
         * dynamically add further fields to #form_has_x__container
         */
        $("#form_has_x__btn").on("click", function(){
            ++form_has_x__number;

            $("#form_has_x__container").append(
                `<br/>

                <div id="form_has_x__entry_` + backmodel_has_areas__number + `">
                    <br/><br/>
                    <label for="form_X__rdfs_label__` + backmodel_has_areas__number + `">Title</label>
                    <input type="text" id="form_X__rdfs_label__` + backmodel_has_areas__number + `" name="form:X__rdfs:label__` + backmodel_has_areas__number + `" value="" required="required">
                    <br/><br/>
                    <input type="text" id="form_X__form_comment__` + backmodel_has_areas__number + `" name="form:X__form:comment__` + backmodel_has_areas__number + `" value="" required="required">
                </div>`
            );

            $("#form_has_x__number").val(form_has_x__number);
        });
    });
</script>',
            $this->fixture->generateFormFor('form:Event')
        );
    }

    /*
     * Tests for transformParameterArrayToDataValidationArray
     */

    public function testTransformParameterArrayToDataValidationArraySimple()
    {
        /*
         * prepare test data set
         */
        $this->commonNamespaces->add('foo', 'http://foo/');

        $this->importTurtle('
            @prefix foo: <'. $this->commonNamespaces->getUri('foo') .'> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .

            foo:type1
                kno:has-property foo:root-prop1 ;
                kno:has-property foo:root-prop2 ;
                kno:has-property foo:label ;
                kno:has-property foo:has-x .

            foo:type2
                kno:has-property foo:label .

            ',
            $this->testGraph
        );

        /*
         * set parameters for function to test
         */
        $typeUri = 'foo:type1';
        $formInput = array(
            '__type'                    => 'foo:type1',
            '__uriSchema'               => 'http://foo/?foo:label?/',
            'foo:root-prop1'            => 'val1',
            'foo:root-prop2'            => 'val2',
            'foo:label'                 => 'val3'
        );

        $res = $this->fixture->transformParameterArrayToDataValidationArray($formInput, $typeUri);

        $this->assertEquals(
            array(
                '_idUri'            => 'http://foo/val3/',
                'foo:root-prop1'    => 'val1',
                'foo:root-prop2'    => 'val2',
                'foo:label'         => 'val3',
            ),
            $res
        );
    }

    public function testTransformParameterArrayToDataValidationArraySimulateSubForm()
    {
        /*
         * prepare test data set
         */
        $this->commonNamespaces->add('foo', 'http://foo/');

        $this->importTurtle('
            @prefix foo: <'. $this->commonNamespaces->getUri('foo') .'> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .

            foo:type1
                kno:has-property foo:root-prop1 ;
                kno:has-property foo:label ;
                kno:has-property foo:has-type2 .

            foo:type2
                kno:has-property foo:label .

            ',
            $this->testGraph
        );

        /*
         * set parameters for function to test
         */
        $typeUri = 'foo:type1';
        $formInput = array(
            '__type'                    => 'foo:type1',
            '__uriSchema'               => 'http://foo/?foo:label?/',
            'foo:root-prop1'            => 'prop1',
            'foo:label'                 => 'label',
            // from a sub formular, which contains data for resources of type foo:type2
            'foo:has-type2__type'       => 'foo:type2',
            'foo:has-type2__uriSchema'  => '%root-uri%area/?foo:label?',
            'foo:type2__foo:label__0'   => 'area1',
            'foo:has-type2__number'     => '1',
        );

        $res = $this->fixture->transformParameterArrayToDataValidationArray($formInput, $typeUri);

        $this->assertEquals(
            array(
                '_idUri'            => 'http://foo/label/',
                'foo:root-prop1'    => 'prop1',
                'foo:label'         => 'label',
                'foo:has-type2'     => array(
                    array(
                        '_idUri'    => 'http://foo/label/area/area1',
                        'foo:label' => 'area1'
                    )
                )
            ),
            $res
        );
    }

    public function testTransformParameterArrayToDataValidationArraySimulateSubFormExistingData()
    {
        /*
         * prepare test data set
         */
        $this->commonNamespaces->add('foo', 'http://foo/');

        $this->importTurtle('
            @prefix foo: <'. $this->commonNamespaces->getUri('foo') .'> .
            @prefix kno: <'. $this->commonNamespaces->getUri('kno') .'> .

            foo:type1
                kno:has-property foo:root-prop1 ;
                kno:has-property foo:label ;
                kno:has-property foo:has-type2 .

            foo:type2
                kno:has-property foo:label .

            ',
            $this->testGraph
        );

        /*
         * set parameters for function to test
         */
        $typeUri = 'foo:type1';
        $formInput = array(
            '__idUri'                   => 'http://existing/resource/',
            '__type'                    => 'foo:type1',
            'foo:root-prop1'            => 'prop1',
            'foo:label'                 => 'label',
            // from a sub formular, which contains data for resources of type foo:type2
            'foo:has-type2__type'       => 'foo:type2',
            'foo:type2____idUri__0'     => 'http://area/1',
            'foo:type2__foo:label__0'   => 'area1',
            'foo:type2____idUri__1'     => 'http://area/2',
            'foo:type2__foo:label__1'   => 'area2',
            'foo:has-type2__number'     => '2',
        );

        $res = $this->fixture->transformParameterArrayToDataValidationArray($formInput, $typeUri);

        $this->assertEquals(
            array(
                '_idUri'            => 'http://existing/resource/',
                'foo:root-prop1'    => 'prop1',
                'foo:label'         => 'label',
                'foo:has-type2'     => array(
                    array(
                        '_idUri'    => 'http://area/1',
                        'foo:label' => 'area1'
                    ),
                    array(
                        '_idUri'    => 'http://area/2',
                        'foo:label' => 'area2'
                    ),
                )
            ),
            $res
        );
    }
}

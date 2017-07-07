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
            array($this->testGraph),
            $this->dataBlankHelper,
            $this->rdfHelpers,
            $this->commonNamespaces
        );
    }

    /*
     * Tests for buildUriByUriSchema
     */

    public function testBuildUriByUriSchema()
    {
        $propertyBlank = $this->dataBlankHelper->createDataBlank();

        $this->assertEquals(
            'http://foo/was-geht/',
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
            'http://bar/was-geht/',
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
                kno:has-property form:has-areas .

            form:has-areas
                kno:restriction-reference-is-of-type form:Area .

            form:located-in
                rdfs:label "Findet statt in"@de ;
                rdfs:label "Located in"@en .

            form:Area
                kno:has-property rdfs:label ;
                kno:has-property form:comment .

            rdfs:label
                rdfs:label "Title"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        // echo $this->fixture->generateFormFor('form:Event'); return;

        $this->assertEquals(
'<form action="" method="post">
    <input type="hidden" name="__type" value="form:Event">
    <input type="hidden" name="__uriSchema" value="">

    <br/>
    <label for="form_located_in">Findet statt in</label>
    <input type="text" id="form_located_in" name="form:located-in" value="" required="required">

    <div id="form_has_areas__container">
        <input type="hidden" name="form:has-areas__type" value="form:Area">
        <input type="hidden" name="form:has-areas__uriSchema" value="">
        <div id="form:has-areas__entry_1">

            <br/>
            <label for="form_Area__rdfs_label__1">Title</label>
            <input type="text" id="form_Area__rdfs_label__1" name="form:Area__rdfs:label__1" value="" required="required">

            <br/>
            <input type="text" id="form_Area__form_comment__1" name="form:Area__form:comment__1" value="" required="required">
        </div>
    </div>
    <input type="hidden" id="form_has_areas__number" name="form:has-areas__number" value="1"/>
    <button class="btn btn-primary" id="form_has_areas__btn" type="button">Add</button>

    <br/><br/>
    <button class="btn btn-primary" type="submit">Submit</button>
</form>


<script type="text/javascript">
    var form_has_areas__number = 1;
    $(document).ready(function(){
        /*
         * dynamically add further fields to #form_has_areas__container
         */
        $("#form_has_areas__btn").on("click", function(){
            ++form_has_areas__number;

            $("#form_has_areas__container").append(`
                <br/>
        <div id="form:has-areas__entry_1">

            <br/>
            <label for="form_Area__rdfs_label__1">Title</label>
            <input type="text" id="form_Area__rdfs_label__1" name="form:Area__rdfs:label__1" value="" required="required">

            <br/>
            <input type="text" id="form_Area__form_comment__1" name="form:Area__form:comment__1" value="" required="required">
        </div>
                `
                .replace(/_entry_(\d)/g, "_entry_" + backmodel_has_areas__number)
                .replace(/__\d"/g, "__" + backmodel_has_areas__number + "\"")
            );

            $("#form_has_areas__number").val(form_has_areas__number);
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
            'foo:type2__foo:label__1'   => 'area1',
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
}

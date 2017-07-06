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

            form:Area
                kno:has-property form:label ;
                kno:has-property form:comment .

            form:located-in
                rdfs:label "Findet statt in"@de ;
                rdfs:label "Located in"@en .
            ',
            $this->testGraph,
            $this->store
        );

        $this->commonNamespaces->add('form', 'http://form/');

        $this->assertEquals(
'<form action="" method="post">
    <input type="hidden" name="rdf:type" value="form:Event">
    <br/><label for="form_located_in">Findet statt in</label>
    <input type="text" id="form_located_in" name="form:located-in" value="" required="required">
    <div id="form_has_areas__container">
        <div id="form:has-areas__entry_1">
            <input type="hidden" name="rdf:type__1" value="form:Area">
            <br/><input type="text" id="form_label__1" name="form:label__1" value="" required="required">
            <br/><input type="text" id="form_comment__1" name="form:comment__1" value="" required="required">
        </div>
    </div>
    <input type="hidden" name="form_has_areas__number"/>
    <button class="btn btn-primary" id="form_has_areas__btn" type="button">Add</button>
</form>


<script type="text/javascript">
    var form_has_areas__number = 1;
    $(document).ready(function(){
        /*
         * dynamically add further area fields
         */
        $("#form_has_areas__btn").on("click", function(){
            ++form_has_areas__number;

            $("#form_has_areas__container").append(`
                <br/>
        <div id="form:has-areas__entry_1">
            <input type="hidden" name="rdf:type__1" value="form:Area">
            <br/><input type="text" id="form_label__1" name="form:label__1" value="" required="required">
            <br/><input type="text" id="form_comment__1" name="form:comment__1" value="" required="required">
        </div>
            `.replace("_entry_1", "_entry_" + form_has_areas__number));

            $("#form_has_areas__number").val(form_has_areas__number);
        });
    });
</script>',
            $this->fixture->generateFormFor('form:Event')
        );
    }
}

<?php

namespace Knorke;

use Knorke\DataBlankHelper;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\RdfHelpers;
use Saft\Store\Store;

class Form
{
    protected $commonNamespaces;
    protected $dataBlankHelper;
    protected $graphs;
    protected $rdfHelpers;


    /**
     * @param DataBlankHelper $dataBlankHelper
     * @param RdfHelpers $rdfHelpers
     */
    public function __construct(
        Store $store,
        array $graphs,
        DataBlankHelper $dataBlankHelper,
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->graphs = $graphs;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;
    }

    /**
     * @param array $graphs Array of NamedNode instances
     */
    protected function buildGraphsList(array $graphs) : string
    {
        $fromGraphList = array();

        foreach ($graphs as $graph) {
            $fromGraphList[] = 'FROM <'. $graph->getUri(). '>';
        }

        return implode(' ', $fromGraphList);
    }

    protected function generateButton(string $id, string $label)
    {
        return '<button class="btn btn-primary" id="'. $id .'__btn" type="button">'. $label .'</button>';
    }

    /**
     * @param string $typeUri
     * @param string $parentTypeUri Optional, default is null
     * @param int $level Optional, default is 1
     * @return string Generated HTML representing one form
     */
    public function generateFormFor(
        string $typeUri,
        string $propertyUri = null,
        string $parentTypeUri = null,
        int $level = 1
    ) : string {
        $blank = $this->dataBlankHelper->load($typeUri);

        $typeHasToHaveProperties = array();

        // collect all has-property objects
        foreach ($blank as $key => $value) {
            if ('kno:has-property' == $key) {
                if (!is_array($value)) {
                    $value = array($value);
                }

                foreach ($value as $blank) {
                    $typeHasToHaveProperties[] = $blank['_idUri'];
                }
            }
        }

        if (0 == count($typeHasToHaveProperties)) return '';

        $spacesBefore = '';
        for ($i = 0; $i < $level*4; $i++) { $spacesBefore .= ' '; }

        /*
         * based on the has-property information, we build the form
         */

        $html = '';
        $javascript = '';
        $suffix = '';

        // no parent type given, add form head
        if (!$this->rdfHelpers->simpleCheckUri($parentTypeUri)) {
            $html = '<form action="   " method="post">';

        // if given, put div container around properties
        } else {
            $html = $spacesBefore;
            $html .= '<div id="'. $propertyUri .'__entry_'. ($level-1) .'">';

            $spacesBefore .= '    ';
            $suffix = '__' . ($level-1);
        }

        // type info
        $html .= PHP_EOL . $spacesBefore . '<input type="hidden" name="rdf:type'. $suffix .'" value="'. $typeUri .'">';

        // go through required properties
        foreach ($typeHasToHaveProperties as $propertyUri) {
            $propertyBlank = $this->dataBlankHelper->load($propertyUri);

            // handle restriction-reference-is-of-type on a property
            if (isset($propertyBlank['kno:restriction-reference-is-of-type'])) {
                $propId = $this->getHtmlFriendlyUri($propertyUri);

                $html .= PHP_EOL . $spacesBefore . '<div id="'. $propertyUri .'__container">';

                /*
                 * add sub form
                 */
                $subForm = $this->generateFormFor(
                    $propertyBlank['kno:restriction-reference-is-of-type']['_idUri'],
                    $propertyUri,
                    $typeUri,
                    $level+1
                );
                $html .= PHP_EOL . $subForm;

                $html .= PHP_EOL . $spacesBefore . '</div>';

                $html .= PHP_EOL . $spacesBefore . '<input type="hidden" name="'. $propId .'__number"/>';

                // button to add more
                $html .= PHP_EOL . $spacesBefore . $this->generateButton($propId, 'Add');

                // js
                $javascript .= PHP_EOL . $this->generateJavascriptForSubResources($propId, $subForm);

            } else {
                $html .= PHP_EOL . $spacesBefore . $this->getInputTextFor($propertyUri, '', $level);
            }

            // TODO kno:restriction-one-of as select?
            // TODO care about kno:inherits-all-properties-of?
        }

        if (!$this->rdfHelpers->simpleCheckUri($parentTypeUri)) {
            $html .= PHP_EOL . '</form>';
        } else {
            $html .= PHP_EOL . substr($spacesBefore, 4) . '</div>';
        }

        // add plain javascript
        if (0 < strlen($javascript)) {
            $html .= PHP_EOL . $javascript;
        }

        return $html;
    }

    /**
     *
     */
    protected function generateJavascriptForSubResources(string $id, string $subFormHTML, int $number = 1)
    {
        return
'<script type="text/javascript">
    var number_of_'. $id .' = 1;
    $(document).ready(function(){
        /*
         * dynamically add further area fields
         */
        $("#'. $id .'").on("click", function(){
            ++number_of_'. $id .';

            $("#'. $id .'__btn").append(`
                <br/>'. PHP_EOL . $subFormHTML .'
            `.replace("_entry_1", "_entry_" + number_of_'. $id .'));

            $("#'. $id .'__number").val('. $id .'__number);
        });
    });
</script>';
    }

    /**
     * Because rdfs:label sucks as ID in HTML, we need something like rdfs_label.
     */
    protected function getHtmlFriendlyUri(string $uri) : string
    {
        return preg_replace('/\W+/', '_', strtolower($uri));
    }

    /**
     * @param string $name
     * @param string $value Optional, default is ''
     * @param string $level Optional, default is 1
     * @return string Ready-to-use HTML with given values.
     */
    public function getInputTextFor(string $name, string $value = '', int $level = 1) : string
    {
        // suffix for property name like rdfs:label ==> rdfs:label
        $suffix = '';
        if (1 < $level) {
            $suffix = '__'. ($level-1);
        }

        $id = $this->getHtmlFriendlyUri($name) . $suffix;
        $name = $name . $suffix;

        $spacesBefore = '';
        for ($i = 0; $i < $level*4; $i++) { $spacesBefore .= ' '; }

        $html = '';
        if ($this->rdfHelpers->simpleCheckURI($name)) {
            $blank = $this->dataBlankHelper->load($name);

            // TODO add title helper here
            if (isset($blank['rdfs:label'])) {
                $html = '<label for="'. $id .'">'. $blank['rdfs:label'][0] .'</label>';
                $html .= PHP_EOL . $spacesBefore;
            }
        }

        $html .= '<input type="text" id="'. $id .'" name="'. $name .'" value="'. $value .'" required="required">';

        return $html;
    }

    /**
     * @param string $name
     * @param string $value Optional, default is ''
     * @return string Ready-to-use HTML with given values.
     */
    public function getSelectBoxFor(string $name, array $options) : string
    {
        $html = '<select name="'. $name .'" required="required">';

        foreach ($options as $value => $label) {
            $html .= PHP_EOL .'    <option value="'. $value .'">'. $label .'</option>';
        }

        return $html . PHP_EOL . '</select>';
    }
}

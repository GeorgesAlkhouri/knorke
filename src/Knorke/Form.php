<?php

namespace Knorke;

use Knorke\DataBlankHelper;
use Knorke\Exception\KnorkeException;
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

    /**
     * @param string $schema
     * @param DataBlank $typeBlank
     * @param array $rawFormInput
     * @param string $rootUri Optional, default is null
     * @return string
     */
    public function buildUriByUriSchema(
        string $schema,
        DataBlank $typeBlank,
        array $rawFormInput,
        string $rootUri = null
    ) : string {

        if (empty($schema)) {
            throw new KnorkeException('Parameter $schema is empty.');
        }

        // if schema looks like %root-uri%/?rdfs:label?, replace root-uri placeholder
        if ($this->rdfHelpers->simpleCheckUri($rootUri)) {
            $schema = str_replace('%root-uri%', $rootUri, $schema);
        }

        // pregmatch to search for placeholders
        preg_match_all('/\?(.*)\?/si', $schema, $placeholders);

        // return schema, because no placeholders where found
        if (0 == count($placeholders)) {
            return $schema;
        }

        // because first entry is the whole phrase
        unset($placeholders[0]);

        $uri = $schema;

        foreach ($placeholders as $value) {
            if (isset($rawFormInput[$value[0]])) {
                $uri = str_replace('?'. $value[0] .'?', $rawFormInput[$value[0]], $uri);
            } else {
                throw new KnorkeException(
                    'Placeholder '. $value[0] .' could not be replaced in URI schema: ' . $schema
                );
            }
        }

        return $uri;
    }

    /**
     * @paraam string $id
     * @paraam string $label
     */
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
            $html = '<form action="" method="post">';

            // type info
            $html .= PHP_EOL . $spacesBefore . '<input type="hidden" name="__type" value="'. $typeUri .'">';
            $html .= PHP_EOL . $spacesBefore . '<input type="hidden" name="__uriSchema" value="">';


        // if parent type given, put div container around properties
        } else {
            $html = $spacesBefore;
            $html .= '<div id="'. $propertyUri .'__entry_'. ($level-1) .'">';

            $spacesBefore .= '    ';
            $suffix = '__' . ($level-1);
        }

        // go through required properties
        foreach ($typeHasToHaveProperties as $propertyUri) {
            $propertyBlank = $this->dataBlankHelper->load($propertyUri);

            // handle restriction-reference-is-of-type on a property
            // TODO decide if we want to support sub-sub-sub... forms
            if (isset($propertyBlank['kno:restriction-reference-is-of-type'])
                && $level < 3) {

                $propId = $this->getHtmlFriendlyUri($propertyUri);
                $referenceTypeUri = $propertyBlank['kno:restriction-reference-is-of-type']['_idUri'];

                $html .= PHP_EOL . PHP_EOL . $spacesBefore;
                $html .= '<div id="'. $propId .'__container">';

                // type info and uri schema for elements of the sub form
                $html .= PHP_EOL . $spacesBefore . '    <input type="hidden" name="'. $propertyUri .'__type" value="'. $referenceTypeUri .'">';
                $html .= PHP_EOL . $spacesBefore . '    <input type="hidden" name="'. $propertyUri .'__uriSchema" value="">';

                /*
                 * add sub form
                 */
                $subForm = $this->generateFormFor(
                    $referenceTypeUri,
                    $propertyUri,
                    $typeUri,
                    $level+1
                );
                $html .= PHP_EOL . $subForm;

                $html .= PHP_EOL . $spacesBefore . '</div>';

                $html .= PHP_EOL . $spacesBefore;
                $html .= '<input type="hidden" id="'. $propId .'__number" name="'. $propertyUri .'__number" value="1"/>';

                // button to add more
                $html .= PHP_EOL . $spacesBefore . $this->generateButton($propId, 'Add');

                // js
                $javascript .= PHP_EOL . $this->generateJavascriptForSubResources($propId, $subForm);

            } else {
                $html .= PHP_EOL . PHP_EOL . $spacesBefore . '<br/>';
                $html .= PHP_EOL . $spacesBefore . $this->getInputTextFor($propertyUri, '', $typeUri, $level);
            }

            // TODO kno:restriction-one-of as select?
            // TODO care about kno:inherits-all-properties-of?
        }

        if (!$this->rdfHelpers->simpleCheckUri($parentTypeUri)) {
            // add submit button
            $html .= PHP_EOL . PHP_EOL . $spacesBefore;
            $html .= '<br/><br/>' . PHP_EOL;
            $html .= $spacesBefore . '<button class="btn btn-primary" type="submit">Submit</button>';

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
        return '
<script type="text/javascript">
    var '. $id .'__number = 1;
    $(document).ready(function(){
        /*
         * dynamically add further fields to #'. $id .'__container
         */
        $("#'. $id .'__btn").on("click", function(){
            ++'. $id .'__number;

            $("#'. $id .'__container").append(`
                <br/>'. PHP_EOL . $subFormHTML .'
                `
                .replace(/_entry_(\d)/g, "_entry_" + backmodel_has_areas__number)
                .replace(/__\d"/g, "__" + backmodel_has_areas__number + "\"")
            );

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
     * @param string $propertyUri
     * @param string $value Optional, default is ''
     * @param string $typeUri Optional, default is null
     * @param string $level Optional, default is 1
     * @return string Ready-to-use HTML with given values.
     */
    public function getInputTextFor(
        string $propertyUri,
        string $value = '',
        string $typeUri = null,
        int $level = 1
    ) : string {
        // suffix for property name like rdfs:label ==> rdfs:label
        $suffix = '';
        if (1 < $level) {
            $suffix = '__'. ($level-1);
        }

        $id = $this->getHtmlFriendlyUri($typeUri) .'__'. $this->getHtmlFriendlyUri($propertyUri) . $suffix;
        $name = $typeUri .'__'. $propertyUri . $suffix;

        $spacesBefore = '';
        for ($i = 0; $i < $level*4; $i++) { $spacesBefore .= ' '; }

        $html = '';
        if ($this->rdfHelpers->simpleCheckURI($propertyUri)) {
            $blank = $this->dataBlankHelper->load($propertyUri);

            // TODO add title helper here
            if (isset($blank['rdfs:label'])) {
                if (is_array($blank['rdfs:label'])) {
                    $label = $blank['rdfs:label'][0];
                } else {
                    $label = $blank['rdfs:label'];
                }
                $html = '<label for="'. $id .'">'. $label .'</label>';
                $html .= PHP_EOL . $spacesBefore;

                if (1 < $level) {
                     $html .= '    ';
                }
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

    /**
     * Expects an array with form input, with a form generated by this class itself. This
     * function will transform it to an array with like:
     *
     *      array(
     *          'http://1/' => 'http://1/2',
     *          'http://2/' => array(
     *              0 => 'http://2/1',
     *              'http://2/2',
     *          ),
     *          ...
     *
     * @param array $formInput
     * @param string $rootElementUri Optional, default is null
     * @return array
     */
    public function transformParameterArrayToDataValidationArray(
        array $formInput,
        string $rootElementUri = null
    ) : array {
        /*
            $formInput looks like:

            array (
                '__type'                    => 'foo:Event'
                '__uriSchema'               => ''
                'foo:startDate'             => 'startd'
                'foo:endDate'               => 'endd'
                'foo:has-areas__type'       => 'foo:Area'
                'foo:has-areas__uriSchema'  => ''
                'rdfs:label__1'             => 'area1'
                'foo:has-areas__number'     => '1'
                ...
            )
        */

        /*
         * Data validation
         */
        if (false == isset($formInput['__type'])) {
            throw new KnorkeException(
                'Field __type on root level of $formInput missing. I dont know the type of the root resource.'
            );
        }

        if (false == isset($formInput['__uriSchema'])) {
            throw new KnorkeException(
                'Field __uriSchema on root level of $formInput missing. I dont know the type of the root resource.'
            );
        }

        $typeUri = $this->commonNamespaces->extendUri($formInput['__type']);
        if (false == $this->rdfHelpers->simpleCheckURI($typeUri)) {
            throw new KnorkeException('$formInput[__type] is not a valid URI: '. $typeUri);
        }

        // check given $typeUri, if further data about it are available
        $typeBlank = $this->dataBlankHelper->load($typeUri);

        if (2 > count($typeBlank)) {
            throw new KnorkeException('For resource $typeUri no further triples could be found in the store: '. $typeUri);
        }

        // check for has-property relations
        if (false == isset($typeBlank['kno:has-property']) || 0 == count($typeBlank['kno:has-property'])) {
            throw new KnorkeException('$typeUri has no has-property properties: '. $typeUri);
        }

        // set uri of root entry
        $result = array(
            '_idUri' => $this->buildUriByUriSchema(
                $formInput['__uriSchema'],
                $typeBlank,
                $formInput,
                $rootElementUri
            )
        );

        $rootProperties = array();
        // property is directly accessible
        if (isset($typeBlank['kno:has-property']['_idUri'])) {
            $rootProperties = array($typeBlank['kno:has-property']);
        } else {
            $rootProperties = $typeBlank['kno:has-property'];
        }

        /*
         * go through gathered data from the store.
         * important:   even if the given array contains more information (e.g. properties) as available
         *              in the store, only these in the store are used.
         */
        foreach ($rootProperties as $propertyBlank) {
            // data on this level are only related to the root resource
            // $propertyUri = foo:startDate, $object = '2017-22-04'
            $propertyUri = $propertyBlank['_idUri'];

            // check direct property-object relation
            if (isset($formInput[$propertyUri])) {
                $result[$propertyUri] = $formInput[$propertyUri];
                continue;

            // check sub form
            } elseif (isset($formInput[$propertyUri . '__type'])) {
                /*
                    if set, then we expect somehing like:

                    array(
                        "foo:has-x__type"          => "foo:type2"
                        "foo:has-x__uriSchema"     => ""
                        "foo:type1__foo:label__1"  => "area1"
                        "foo:has-x__number"        => "1"

                    to objective is to remove prefixes (e.g. foo:has-x) and call this function
                    with adapted values again and merge result later on.
                 */
                // TODO check that all meta data are available in an extra function

                $numberOfSubEntries = $formInput[$propertyUri . '__number'];

                // put reduction into a seperate function
                $reducedFormInput = array(
                    '__type'        => $formInput[$propertyUri . '__type'],
                    '__uriSchema'   => $formInput[$propertyUri . '__uriSchema']
                );

                $subPropertyBlank = $this->dataBlankHelper->load($reducedFormInput['__type']);

                /*
                 * data validation of the sub resource(s)
                 */
                // TODO move validation to separate functions
                if (2 > count($subPropertyBlank)) {
                    throw new KnorkeException('Sub type property has no triples: '. $typeOfSubEntries);
                // check for has-property relations
                } elseif (false == isset($subPropertyBlank['kno:has-property'])
                    || 0 == count($subPropertyBlank['kno:has-property'])) {
                    throw new KnorkeException('Sub type property has no has-property properties: '. $typeOfSubEntries);
                }

                // build properties array
                $properties = $subPropertyBlank['kno:has-property'];
                // property is directly accessible
                if (isset($subPropertyBlank['kno:has-property']['_idUri'])) {
                    $properties = array($subPropertyBlank['kno:has-property']);
                } else {
                    $properties = $subPropertyBlank['kno:has-property'];
                }

                for ($subEntryIndex = 1; $subEntryIndex <= $numberOfSubEntries; ++$subEntryIndex) {
                    foreach ($properties as $blank) {
                        // e.g. $formInput['foo:Area__foo:label__1']
                        $reducedFormInput[$blank['_idUri']]
                            = $formInput[$reducedFormInput['__type'] .'__'. $blank['_idUri'] .'__'. $subEntryIndex];
                    }
                }

                $result[$propertyUri][] = $this->transformParameterArrayToDataValidationArray(
                    $reducedFormInput,
                    $result['_idUri']
                );
            }
        }

        return $result;
    }
}

<?php

namespace Knorke;

use Knorke\DataBlankHelper;
use Knorke\Exception\KnorkeException;
use Knorke\Form\TwigHtmlGenerator;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\RdfHelpers;
use Saft\Store\Store;

class Form
{
    protected $commonNamespaces;
    protected $configuration;
    protected $dataBlankHelper;
    protected $graphs;
    protected $rdfHelpers;


    /**
     * @param DataBlankHelper $dataBlankHelper
     * @param RdfHelpers $rdfHelpers
     */
    public function __construct(
        Store $store,
        DataBlankHelper $dataBlankHelper,
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces,
        array $configuration = array()
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->rdfHelpers = $rdfHelpers;
        $this->store = $store;

        // configuration
        $this->configuration = array_merge(
            array(
                'form' => array(
                    'action_url' => null, // action property of a <form> DOM element
                )
            ),
            $configuration
        );

        $this->htmlGenerator = new TwigHtmlGenerator();
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
        if ($this->rdfHelpers->simpleCheckUri($rootUri) || false !== strpos($schema, ':')) {
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
                $uri = str_replace('?'. $value[0] .'?', $this->getHtmlFriendlyIdentifier($rawFormInput[$value[0]]), $uri);
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
     * @return array|string
     */
    public function generateFormFor(
        string $typeUri,
        string $propertyUri = null,
        string $parentTypeUri = null,
        int $level = 1
    ) {

        $blank = $this->dataBlankHelper->load($typeUri);

        /*
         * collect all has-property objects
         */
        $typeHasToHaveProperties = array();
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

        /*
         * based on the has-property information, we build the form
         */

        $htmlElements = array();
        $javascript = '';
        $suffix = '';

        // no parent type given, add form head
        if (!$this->rdfHelpers->simpleCheckUri($parentTypeUri)) {

            // shortcut for action-url of the form
            $url = $this->configuration['form']['action_url'];

            $htmlElements[] = '<form method="post" action="'. $url .'">';

            // type info
            $htmlElements[] = '<input type="hidden" name="__type" value="'. $typeUri .'">';

            /*
             * add either uri schema information (insert) or __idUri (update)
             */
            // add __idUri information with resource URI
            $htmlElements[] = '{% if root_item["_idUri"] is defined %}';
            $htmlElements[] = '<input type="hidden" name="__idUri" value="{{ root_item["_idUri"] }}">';
            $htmlElements[] = '{% else %}';
            // add uri schema if new item is to create
            $htmlElements[] = '<input type="hidden" name="__uriSchema" value="">';
            $htmlElements[] = '{% endif %}';

        // if parent type given, put div container around properties
        } else {
            $htmlElements[] = '<div id="'. $propertyUri .'__entry_'. ($level-1) .'">';
            $suffix = '__' . ($level-1);
        }

        // go through required properties
        foreach ($typeHasToHaveProperties as $propertyUri) {
            $propertyBlank = $this->dataBlankHelper->load($propertyUri);

            // handle restriction-reference-is-of-type on a property
            // TODO decide if we want to support sub-sub-sub... forms
            if (isset($propertyBlank['kno:restriction-reference-is-of-type']) && $level < 3) {

                $propId = $this->getHtmlFriendlyIdentifier($propertyUri);
                $referencedTypeBlank = $propertyBlank['kno:restriction-reference-is-of-type'];
                $referencedTypeBlank->initBySelfSearch();
                $referenceTypeUri = $propertyBlank['kno:restriction-reference-is-of-type']['_idUri'];

                $htmlElements[] = '<div id="'. $propId .'__container">';

                // type info and uri schema for elements of the sub form
                $htmlElements[] = '<input type="hidden" name="'. $propertyUri .'__type" '.
                                         'value="'. $referenceTypeUri .'">';
                $htmlElements[] = '<input type="hidden" name="'. $propertyUri .'__uriSchema" value="">';

                /*
                 * add loop to show existing entries for this property relation, if available
                 */
                $htmlElements[] = '{% if root_item["'. $propertyUri .'"] is defined %}';
                $htmlElements[] = '{% for key,sub_item in root_item["'. $propertyUri .'"] %}';
                $htmlElements[] = '<div id="'. $propertyUri .'__entry_{{key}}">';
                $htmlElements[] = '<input type="hidden" name="'. $referenceTypeUri .'____idUri__{{key}}" '.
                                         'value="{{ sub_item["_idUri"] }}">';

                $htmlFriendlyRefTypeUri = $this->getHtmlFriendlyIdentifier($referenceTypeUri); // e.g. foo:BarClass
                $htmlFriendlyPropUri = $this->getHtmlFriendlyIdentifier($referenceTypeUri);   // e.g. foo:bazProperty;
                unset($referencedTypeBlank['_idUri']);
                foreach ($referencedTypeBlank->getPropertyAsArrayOfItems('kno:has-property') as $entry) {
                    $uri = $entry['_idUri'];
                    $htmlFriendlyUri = $this->getHtmlFriendlyIdentifier($entry['_idUri']);
                    $id = $htmlFriendlyRefTypeUri .'__'. $htmlFriendlyUri .'__{{key}}';

                    if (isset($entry['rdfs:label'])) {
                        $htmlElements[] = '<label for="'. $id .'">'. $entry['rdfs:label']->getValue() .'</label>';
                    }

                    // go through sub_item properties
                    $htmlElements[] = '<input type="text" id="'. $id .'" '
                        . 'name="'. $referenceTypeUri .'__'. $uri .'__{{key}}" '
                        . 'value="{{ sub_item["'. $uri .'"] }}" required="required">';
                }

                $htmlElements[] = '</div>';
                $htmlElements[] = '{% endfor %}';
                $htmlElements[] = '{% endif %}';

                /*
                 * add sub form
                 */
                $subForm = $this->generateFormFor(
                    $referenceTypeUri,
                    $propertyUri,
                    $typeUri,
                    $level+1
                );

                $htmlElements = array_merge($htmlElements, $subForm);

                $htmlElements[] = '</div>';

                /*
                 * add if else block to provide either a fresh __number field or one for existing values
                 */
                $htmlElements[] = '{% if root_item["'. $propertyUri .'"] is defined %}';
                $htmlElements[] = '<input type="hidden" id="'. $propId .'__number" '
                                         . 'name="'. $propertyUri .'__number" '
                                         . 'value="{{ root_item["'. $propertyUri .'"]|length }}"/>';
                $htmlElements[] = '{% else %}';
                $htmlElements[] = '<input type="hidden" id="'. $propId .'__number" '
                                       . 'name="'. $propertyUri .'__number" value="1"/>';
                $htmlElements[] = '{% endif %}';

                // button to add more
                $htmlElements[] = $this->generateButton($propId, 'Add');

                // js
                $javascript .= PHP_EOL . $this->generateJavascriptForSubResources($propId, $subForm);

            } else {
                $htmlElements[] = '<br/><br/>';

                if (1 == $level) {
                    $htmlElements = array_merge(
                        $htmlElements,
                        $this->getInputTextFor($propertyUri)
                    );
                } else {
                    $htmlElements = array_merge(
                        $htmlElements,
                        $this->getInputTextFor($propertyUri, $typeUri, $level)
                    );
                }
            }

            // TODO kno:restriction-one-of as select?
            // TODO care about kno:inherits-all-properties-of?
        }

        if (!$this->rdfHelpers->simpleCheckUri($parentTypeUri)) {
            // add submit button
            $htmlElements[] = '<br/><br/>';
            $htmlElements[] = '<button class="btn btn-primary" type="submit">Save</button>';

            // add field to tell the program, if its an update or creation
            $htmlElements[] = '{% if root_item["_idUri"] is defined %}';
            $htmlElements[] = '<input type="hidden" name="action" value="update">';
            $htmlElements[] = '{% else %}';
            $htmlElements[] = '<input type="hidden" name="action" value="create">';
            $htmlElements[] = '{% endif %}';

            $htmlElements[] = '</form>';
        } else {
            $htmlElements[] = '</div>';
        }

        if (!$this->rdfHelpers->simpleCheckUri($parentTypeUri)) {
            // put all the HTML stuff together and produce cool, correctly indented HTML
            $html = $this->htmlGenerator->transformFormArrayToCoolHtml($htmlElements);

            // add plain javascript
            if (0 < strlen($javascript)) {
                $html .= PHP_EOL . $javascript;
            }

            return $html;
        } else {
            // add plain javascript
            if (0 < strlen($javascript)) {
                $htmlElements[] = PHP_EOL . $javascript;
            }

            return $htmlElements;
        }
    }

    /**
     * @param string $id
     * @param array $subFormElements
     * @param int number Optional, default is 1
     */
    protected function generateJavascriptForSubResources(string $id, array $subFormElements, int $number = 1)
    {
        $subFormHTML = $this->htmlGenerator->transformFormArrayToCoolHtml($subFormElements, 4);

        return '
<script type="text/javascript">
    var '. $id .'__number = 1;
    $(document).ready(function(){
        /*
         * dynamically add further fields to #'. $id .'__container
         */
        $("#'. $id .'__btn").on("click", function(){
            ++'. $id .'__number;

            $("#'. $id .'__container").append(
                `<br/>'. PHP_EOL . $subFormHTML .'`
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
     *
     * @param string $uri
     * @return string HTML friendly URI which can be used as name or ID for HTML DOM elements.
     */
    protected function getHtmlFriendlyIdentifier(string $uri) : string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $uri);
    }

    /**
     * @param string $propertyUri
     * @param string $typeUri Optional, default is null
     * @return array
     */
    public function getInputTextFor(string $propertyUri, string $typeUri = null, int $level = 1) : array
    {
        // suffix for property name like rdfs:label ==> rdfs:label
        $suffix = '';
        if (1 < $level) {
            $suffix = '__'. ($level-1);
            $itemName = 'sub_item';
        } else {
            $itemName = 'root_item';
        }

        if (null !== $typeUri) {
            $id = $this->getHtmlFriendlyIdentifier($typeUri) .'__'. $this->getHtmlFriendlyIdentifier($propertyUri) . $suffix;
            $name = $typeUri .'__'. $propertyUri . $suffix;
        } else {
            $id = $this->getHtmlFriendlyIdentifier($propertyUri) . $suffix;
            $name = $propertyUri . $suffix;
        }

        $htmlElements = array();
        if ($this->rdfHelpers->simpleCheckURI($propertyUri)) {
            $blank = $this->dataBlankHelper->load($propertyUri);

            // TODO add title helper here
            if (isset($blank['rdfs:label'])) {
                if (is_array($blank['rdfs:label'])) {
                    $label = $blank['rdfs:label'][0];
                } else {
                    $label = $blank['rdfs:label'];
                }
                $htmlElements[] = '<label for="'. $id .'">'. $label .'</label>';
            }
        }

        $value = '{% if '. $itemName .'["'. $propertyUri .'"] is defined %}'
            . '{{ '. $itemName .'["'. $propertyUri .'"] }}'
            . '{% endif %}';

        $htmlElements[] = '<input type="text" id="'. $id .'" '
                                . 'name="'. $name .'" '
                                . 'value="'. $value .'" required="required">';

        return $htmlElements;
    }

    /**
     * @param string $name
     * @param string $value Optional, default is ''
     * @return array
     */
    public function getSelectBoxFor(string $name, array $options) : array
    {
        $htmlElements = array();

        $htmlElements[] = '<select name="'. $name .'" required="required">';

        foreach ($options as $value => $label) {
            $htmlElements[] = '<option value="'. $value .'">'. $label .'</option>';
        }

        $htmlElements[] = '</select>';

        return $htmlElements;
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

        if (isset($formInput['__idUri'])) {
            // existing resource given
        } elseif (false == isset($formInput['__uriSchema'])) {
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

        if (false == isset($formInput['__idUri'])) {
            $resourceUri = $this->buildUriByUriSchema(
                $formInput['__uriSchema'],
                $typeBlank,
                $formInput,
                $rootElementUri
            );
        } else {
            $resourceUri = $formInput['__idUri'];
        }

        // set uri of root entry
        $result = array('_idUri' => $resourceUri);

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

                            -- or --

                    array(
                        "foo:has-x__type"          => "foo:type2"
                        "foo:type1____idUri__1"    => "http://area/1"
                        "foo:type1__foo:label__1"  => "area1"
                        "foo:has-x__number"        => "1"

                    to objective is to remove prefixes (e.g. foo:has-x) and call this function
                    with adapted values again and merge result later on.
                 */
                // TODO check that all meta data are available in an extra function

                // put reduction into a seperate function
                $reducedFormInput = array(
                    '__type'        => $formInput[$propertyUri . '__type']
                );

                if (isset($formInput[$propertyUri . '__uriSchema'])) {
                    $reducedFormInput['__uriSchema'] = $formInput[$propertyUri . '__uriSchema'];
                }

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

                // for each sub entry
                $numberOfSubEntries = $formInput[$propertyUri . '__number'];
                for ($subEntryIndex = 0; $subEntryIndex < $numberOfSubEntries; ++$subEntryIndex) {
                    foreach ($properties as $blank) {
                        // e.g. $formInput['foo:Area__foo:label__1']
                        $reducedFormInput[$blank['_idUri']]
                            = $formInput[$reducedFormInput['__type'] .'__'. $blank['_idUri'] .'__'. $subEntryIndex];
                    }

                    // add __idUri information, if available
                    if (isset($formInput[$reducedFormInput['__type'] .'____idUri__'. $subEntryIndex])) {
                        $reducedFormInput['__idUri'] = $formInput[$reducedFormInput['__type'] .'____idUri__'. $subEntryIndex];
                    }

                    /*
                        extends structures like:

                        'foo:has-type2'     => array(
                            array(
                                '_idUri'    => 'http://existing/resource/area/area1',
                                'foo:label' => 'area1'
                            ),
                            ...
                     */
                    $result[$propertyUri][] = $this->transformParameterArrayToDataValidationArray(
                        $reducedFormInput,
                        $result['_idUri']
                    );
                }
            }
        }

        return $result;
    }
}

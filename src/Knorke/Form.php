<?php

namespace Knorke;

use Knorke\DataBlankHelper;
use Knorke\Exception\KnorkeException;
use Knorke\Form\TwigHtmlGenerator;
use Saft\Rdf\CommonNamespaces;
use Saft\Rdf\Node;
use Saft\Rdf\NodeFactory;
use Saft\Rdf\RdfHelpers;
use Saft\Rdf\StatementFactory;
use Saft\Store\Store;

class Form
{
    protected $commonNamespaces;
    protected $configuration;
    protected $dataBlankHelper;
    protected $graphs;
    protected $rdfHelpers;
    protected $restrictions;
    protected $statementFactory;
    protected $store;


    /**
     * @param DataBlankHelper $dataBlankHelper
     * @param RdfHelpers $rdfHelpers
     * @param CommonNamespaces $commonNamespaces
     * @param NodeFactory $nodeFactory
     * @param StatementFactory $statementFactory
     * @param Restriction $restriction
     * @param array $configuration = array()
     */
    public function __construct(
        Store $store,
        DataBlankHelper $dataBlankHelper,
        RdfHelpers $rdfHelpers,
        CommonNamespaces $commonNamespaces,
        NodeFactory $nodeFactory,
        StatementFactory $statementFactory,
        Restriction $restriction,
        array $configuration = array()
    ) {
        $this->commonNamespaces = $commonNamespaces;
        $this->dataBlankHelper = $dataBlankHelper;
        $this->nodeFactory = $nodeFactory;
        $this->rdfHelpers = $rdfHelpers;
        $this->restriction = $restriction;
        $this->statementFactory = $statementFactory;
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
     * @param array      &$array
     * @param int|string $position
     * @param mixed      $insert
     */
    protected function insertArrayAtPosition(&$array, $position, $insert)
    {
        if (is_int($position)) {
            array_splice($array, $position, 0, $insert);
        } else {
            $pos   = array_search($position, array_keys($array));
            $array = array_merge(array_slice($array, 0, $pos), $insert, array_slice($array, $pos));
        }
    }

    /**
     * @param string $schema
     * @param array $rawFormInput
     * @return string
     */
    public function buildRootElementUriByUriSchema(string $schema, array $rawFormInput) : string
    {
        if (empty($schema)) {
            throw new KnorkeException('Parameter $schema is empty.');
        }

        // preg match to search for placeholders
        preg_match_all('/\?(.*)\?/si', $schema, $placeholders);

        // return schema, if no placeholders where found
        if (0 == count($placeholders)
            || 2 == count($placeholders) && isset($placeholders[0]) && 0 == count($placeholders[0])) {
            return $schema;
        }

        // because first entry is the whole phrase
        unset($placeholders[0]);

        $uri = $schema;

        foreach ($placeholders as $value) {
            if (isset($rawFormInput[$value[0]])) {
                $uri = str_replace(
                    '?'. $value[0] .'?',
                    $this->getHtmlFriendlyIdentifier($rawFormInput[$value[0]]),
                    $uri
                );
            } else {
                throw new KnorkeException(
                    'Placeholder '. $value[0] .' could not be replaced in URI schema: ' . $schema
                );
            }
        }

        return $uri;
    }

    /**
     *
     */
    public function buildSubElementUriByUriSchema(
        string $schema,
        array $rawFormInput,
        string $rootElementUri,
        string $rootElementPropertyUri,
        string $subTypeUri,
        $subEntryIndex,
        string $rootUri = null
    ) : string {

        if (empty($schema)) {
            throw new KnorkeException('Parameter $schema is empty.');
        }

        // if schema looks like %root-uri%/?rdfs:label?, replace root-uri placeholder
        if ($this->rdfHelpers->simpleCheckUri($rootUri) || false !== strpos($schema, ':')) {
            $schema = str_replace('%root-uri%', $rootUri, $schema);
        }

        // preg match to search for placeholders
        preg_match_all('/\?(.*)\?/si', $schema, $placeholders);

        // return schema, if no placeholders where found
        if (0 == count($placeholders)
            || 2 == count($placeholders) && isset($placeholders[0]) && 0 == count($placeholders[0])) {
            return $schema;
        }

        // because first entry is the whole phrase
        unset($placeholders[0]);

        $uri = $schema;

        foreach ($placeholders as $value) {
            $key = $rootElementUri
                .'__'. $rootElementPropertyUri
                .'__'. $subTypeUri
                .'__';

            if (isset($rawFormInput[$key . $value[0] . '__'. $subEntryIndex])) {
                $uri = str_replace(
                    '?'. $value[0] .'?',
                    $this->getHtmlFriendlyIdentifier($rawFormInput[$key . $value[0] . '__'. $subEntryIndex]),
                    $uri
                );
            } else {
                throw new KnorkeException(
                    'Placeholder '. $value[0] .' could not be replaced in URI schema: ' . $schema
                );
            }
        }

        return $uri;
    }

    /**
     * @param string $id
     * @param string $label
     * @param string $type Optional, default is 'button'
     * @param string $cssClasses Optional, default is "btn btn-primary"
     */
    public function button(string $id, string $label, $type = 'button', string $cssClasses = 'btn btn-primary')
    {
        return '<button class="'. $cssClasses .'" id="'. $id .'" type="'. $type .'">'. $label .'</button>';
    }

    /**
     * Gets a label for a given resource URI.
     *
     * @param string $uri
     * @return string Label or given URI.
     */
    protected function getLabelFor(string $uri)
    {
        $blank = $this->dataBlankHelper->load($uri);

        // TODO extend that to support further label properties as well as different languages
        if (isset($blank['rdfs:label'])) {
            if (is_array($blank['rdfs:label'])) {
                return $blank['rdfs:label'][0];
            } else {
                return $blank['rdfs:label'];
            }
        }

        return $uri;
    }

    /**
     * @param string $value
     * @return Node
     * @throws \Exception if blank node was given.
     */
    protected function getNodeForGivenValue(string $value) : Node
    {
        // named node
        if ($this->rdfHelpers->simpleCheckUri($value)) {
            return $this->nodeFactory->createNamedNode($this->commonNamespaces->extendUri($value));

        // literal
        } else {
            return $this->nodeFactory->createLiteral($value);
        }
    }

    /**
     * @param string $htmlElement
     * @return null|array Array with 3 elements
     */
    public function getSubFormElements(string $htmlElement)
    {
        // value of $htmlElement for instance looks like:
        // %subForm rootElementUri="(.*?)" rootElementPropertyUri="(.*?)" subElementTypeUri="form:type2" %
        $res = preg_match(
            '/\%subForm rootElementUri="(.*?)" '
                .'rootElementPropertyUri="(.*?)" '
                .'subElementTypeUri="(.*?)"'
                .'/si',
            $htmlElement,
            $matches
        );

        if (4 == count($matches)) {
            return array(
                $matches[1],
                $matches[2],
                $matches[3],
            );
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $value Optional, default is ''
     * @param string $id Optional, default is null
     */
    public function inputFieldHidden(string $name, string $value = '', string $id = null)
    {
        $html = '<input type="hidden" name="'. $name .'" value="'. $value .'"';

        if (0 < strlen($id)) {
            $html .= ' id="'. $id .'"';
        }

        $html .= '>';

        return $html;
    }

    /**
     * @param string $name
     * @param string $value Optional, default is ''
     * @param string $id Optional, default is null
     * @param bool $required Optional, default is false
     */
    public function inputFieldText(string $name, string $value = '', string $id = null, bool $required = false)
    {
        $html = '<input type="text" name="'. $name .'" value="'. $value .'"';

        if (0 < strlen($id)) {
            $html .= ' id="'. $id .'"';
        }

        if ($required) {
            $html .= ' required="required"';
        }

        $html .= '>';

        return $html;
    }

    /**
     * @param string $label
     * @return string
     */
    protected function labelText(string $label)
    {
        return '<strong>'. $label .'</strong><br/>';
    }

    /**
     * Generates a form and, if necessary, subforms.
     *
     * @param string $rootElementUri
     * @return string
     */
    public function generateFormFor(string $rootElementUri)
    {
        $rootElementBlank = $this->dataBlankHelper->load($rootElementUri);

        /*
         * collect all has-property objects
         */
        $properties = array();
        foreach ($rootElementBlank as $key => $value) {
            if ('kno:has-property' == $key) {
                if (!is_array($value)) {
                    $value = array($value);
                }
                foreach ($value as $entry) {
                    $properties[] = $entry['_idUri'];
                }
            /*
             * care about property kno:inherits-all-properties-of.
             * gather all referenced properties and add them to the list.
             */
            } elseif ('kno:inherits-all-properties-of' == $key && isset($value['kno:has-property'])) {
                foreach ($value->getPropertyAsArrayOfItems('kno:has-property') as $subEntry) {
                    $properties[] = $subEntry['_idUri'];
                }
            }
        }

        if (0 == count($properties)) return '';

        $htmlElements   = array();
        $htmlElements[] = '<form method="post" action="'. $this->configuration['form']['action_url'] .'">';
        $htmlElements[] =       $this->inputFieldHidden('__type', $rootElementUri); // type info

        /*
         * add either uri schema information (insert) or __idUri (update)
         */
        // add __idUri information with resource URI
        $htmlElements[] =       '{% if root_item["_idUri"] is defined %}';
        $htmlElements[] =           $this->inputFieldHidden('__idUri', '{{ root_item["_idUri"] }}');
        $htmlElements[] =       '{% else %}';
        // add uri schema if new item is to create
        $htmlElements[] =           $this->inputFieldHidden('__uriSchema');
        $htmlElements[] =       '{% endif %}';

        /*
         * go through all has-property properties
         *
         *  foo:type1
         *      kno:has-property foo:root-prop1 ;
         *      kno:has-property foo:root-prop2 ;
         *      ...
         */
        foreach ($properties as $rootElementPropertyUri) {
            $propertyBlank = $this->dataBlankHelper->load($rootElementPropertyUri);

            /*
             * this restriction ... property requires the inclusion of a sub form.
             * for now, just add a placeholder %subform, which will later be replaced by a
             * sub array.
             */
            if (isset($propertyBlank['kno:restriction-reference-is-of-type'])) {
                $htmlElements[] = '%subForm '
                    . 'rootElementUri="'.         $rootElementUri .'" '
                    . 'rootElementPropertyUri="'. $rootElementPropertyUri .'" '
                    . 'subElementTypeUri="'.      $propertyBlank['kno:restriction-reference-is-of-type']['_idUri'] .'" '
                    . '%';

            /*
             * simple property, there include label, if available, and input field
             */
            } else {
                $htmlElements[] = '<br/><br/>';
                $htmlElements[] = $this->labelText($this->getLabelFor($rootElementPropertyUri));
                $htmlElements[] = $this->inputFieldText(
                    $rootElementPropertyUri,
                    '{% if root_item["'. $rootElementPropertyUri .'"] is defined %}{{ root_item["'. $rootElementPropertyUri .'"] }}{% endif %}'
                );
            }
        }

        /*
         * go through $htmlElements and replace all %subForm ..% entries.
         *
         * example: %subForm rootElementUri="form:type1" rootElementPropertyUri="form:t1-p2" %
         */
        $javascripts = array();
        foreach ($htmlElements as $key => $htmlElement) {
            if ('%subForm' == substr($htmlElement, 0, 8)) {
                // extract meta data about subform to generate
                list($rootElementUri, $rootElementPropertyUri, $subElementTypeUri) = $this->getSubFormElements($htmlElement);

                // replace placeholder with full HTML sub form
                list($subFormHtmlElements, $subFormJs) = $this->generateSubForm(
                    $rootElementUri,
                    $rootElementPropertyUri,
                    $subElementTypeUri
                );

                $javascripts[] = $subFormJs;

                // remove %subForm ... entry
                unset($htmlElements[$key]);

                // merge sub form elements into the major array
                $this->insertArrayAtPosition($htmlElements, $key, $subFormHtmlElements);
            }
        }

        // add hidden field named action which contains insert or update
        $htmlElements[] =       '{% if root_item["_idUri"] is defined %}';
        $htmlElements[] =           $this->inputFieldHidden('action', 'update');
        $htmlElements[] =       '{% else %}';
        $htmlElements[] =           $this->inputFieldHidden('action', 'insert');
        $htmlElements[] =       '{% endif %}';

        // add submit button
        $htmlElements[] =       '<br/><br/>';
        $htmlElements[] =       $this->button('', 'Save', 'submit');

        $htmlElements[] = '</form>';

        return array(
            $this->htmlGenerator->transformFormArrayToCoolHtml($htmlElements),
            $javascripts
        );
    }

    /**
     * Generates a sub form which depends on a root element + a connecting property.
     *
     * @param string $rootElementUri
     * @param string $rootElementPropertyUri
     * @param string $subElementTypeUri
     */
    public function generateSubForm(string $rootElementUri, string $rootElementPropertyUri, string $subElementTypeUri) : array
    {
        $htmlElements = array();

        $htmlFriendlyRootElementPropertyUri = $this->getHtmlFriendlyIdentifier($rootElementPropertyUri);
        // prefix for sub element entries. we need this to be able to align a sub element property to the root element
        $namePrefixForSubElementProperties = $rootElementUri
            . '__' . $rootElementPropertyUri
            . '__' . $subElementTypeUri
            . '__';

        $htmlElements[] = '<div id="'. $htmlFriendlyRootElementPropertyUri .'__container">';

        /*
         * meta data about the given subElement type. we need these, to be able to create further instances if neccessary.
         */
        $htmlElements[] =       $this->inputFieldHidden($rootElementPropertyUri .'__type', $subElementTypeUri);
        $htmlElements[] =       $this->inputFieldHidden($rootElementPropertyUri .'__uriSchema', '');

        /*
         * in case, there are already elements available for the given root element property
         */
        $htmlElements[] =       '{% set entry_count = 0 %}';
        $htmlElements[] =       '{% if root_item["'. $rootElementPropertyUri .'"] is defined %}';
        $htmlElements[] =           '{% for key,sub_item in root_item["'. $rootElementPropertyUri .'"] %}';

        // put a div container around
        $subElementTypeBlank = $this->dataBlankHelper->load($subElementTypeUri);
        $subElements   = array();
        $subElements[] =                '<div class="'. $htmlFriendlyRootElementPropertyUri .'_element">';
        foreach ($subElementTypeBlank['kno:has-property'] as $subElementProperty) {
            $subElementPropertyUri = isset($subElementProperty['_idUri']) ? $subElementProperty['_idUri'] : $subElementProperty;

            // label
            $subElements[] =            $this->labelText($this->getLabelFor($subElementPropertyUri));

            // input field
            $subElements[] =            $this->inputFieldText(
                                            $namePrefixForSubElementProperties . $subElementPropertyUri .'__{{ entry_count }}',
                                            '{{ sub_item["'. $subElementPropertyUri .'"] }}'
                                        );
        }

        // __idUri
        $subElements[] =                $this->inputFieldHidden(
                                            $namePrefixForSubElementProperties . '__idUri__{{ entry_count }}',
                                            '{{ sub_item["_idUri"] }}'
                                        );

        $subElements[] =                '</div>';

        $htmlElements = array_merge($htmlElements, $subElements);

        $htmlElements[] =              '{% set entry_count = entry_count+1 %}';
        $htmlElements[] =           '{% endfor %}';
        $htmlElements[] =       '{% endif %}';

        $htmlElements[] = '</div>';

        $htmlElements[] = $this->button($htmlFriendlyRootElementPropertyUri .'__btn', 'Add', 'button', 'btn btn-primary btn-xs');

        /*
         * if (sub elements) property is defined, stored the number of available sub elements.
         * if no, just store 0.
         */
        $htmlElements[] = '{% if root_item["'. $rootElementPropertyUri .'"] is defined %}';

        $htmlElements[] = $this->inputFieldHidden(
                            $rootElementUri .'__'. $rootElementPropertyUri .'__number',
                            '{{ root_item["'. $rootElementPropertyUri .'"]|length }}',
                            $htmlFriendlyRootElementPropertyUri .'__number'
                          );

        $htmlElements[] = '{% else %}';

        $htmlElements[] = $this->inputFieldHidden(
                            $rootElementUri .'__'. $rootElementPropertyUri .'__number',
                            '0',
                            $htmlFriendlyRootElementPropertyUri .'__number'
                          );

        $htmlElements[] = '{% endif %}';

        /*
         * generate according javascript, which allows addition of further sub forms for this type
         */
        $javascript = $this->generateJavascriptForSubResources($rootElementPropertyUri, $subElements);

        return array($htmlElements, $javascript);
    }

    /**
     * @param string $rootElementPropertyUri URI of the property which can have multiple objects
     * @param array $subFormElements
     * @param int number Optional, default is 1
     */
    protected function generateJavascriptForSubResources(string $rootElementPropertyUri, array $subFormElements)
    {
        $subFormHTML = $this->htmlGenerator->transformFormArrayToCoolHtml($subFormElements, 4);

        // remove buttons
        $subFormHTML = preg_replace('/<button.*<\/button>/si', '', $subFormHTML);

        // replace {{ entry_count }} with a javascript variable
        // the reason is, that JS controls the increasing index, whereas entry_count is controled by twig
        $subFormHTML = str_replace('{{ entry_count }}', '` + backmodel_has_areas__number + `', $subFormHTML);

        // remove twig related content: {% %} and {{ }}
        $subFormHTML = preg_replace('/{%.*?%}/si', '', $subFormHTML);
        $subFormHTML = preg_replace('/{{.*?}}/si', '', $subFormHTML);

        $htmlFriendlyPropertyUri = $this->getHtmlFriendlyIdentifier($rootElementPropertyUri);

        return '
<script type="text/javascript">

    // store latest number of root_item["'. $rootElementPropertyUri .'"] entries
    {% if root_item["'. $rootElementPropertyUri .'"] is defined %}
        var '. $htmlFriendlyPropertyUri .'__number = {{ root_item["'. $rootElementPropertyUri .'"]|length }};
    {% else %}
        var '. $htmlFriendlyPropertyUri .'__number = 0;
    {% endif %}
    $(document).ready(function(){
        /*
         * dynamically add further fields to #'. $htmlFriendlyPropertyUri .'__container
         */
        $("#'. $htmlFriendlyPropertyUri .'__btn").on("click", function(){

            $("#'. $htmlFriendlyPropertyUri .'__container").append(
                `<br/>'. PHP_EOL . $subFormHTML .'`
            );

            ++'. $htmlFriendlyPropertyUri .'__number;

            $("#'. $htmlFriendlyPropertyUri .'__number").val('. $htmlFriendlyPropertyUri .'__number);
        });
    });
</script>';
    }

    /**
     * Because identifier like rdfs:label suck as ID in HTML, we need something like rdfs_label.
     *
     * @param string $uri
     * @return string HTML friendly URI which can be used as name or ID for HTML DOM elements.
     */
    protected function getHtmlFriendlyIdentifier(string $uri) : string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $uri);
    }

    /**
     * Expects an array with form input, with a form generated by this class itself.
     *
     * @param array $formInput
     * @return array Array of Statement instances.
     */
    public function transformParameterArrayToStatementArray(array $formInput) : array
    {
        /*
            $formInput looks like:

            array(
                '__type'                                                     => $typeUri,
                '__idUri'                                                    => 'http://type1/',
                'form:t1-p1'                                                 => 't1-p1-value',
                'form:t1-p2__uriSchema'                                      => '%root-uri%?form:t2-p1?',
                'form:t1-p2__type'                                           => 'form:type2',
                // sub entry 1
                'form:type1__form:t1-p2__form:type2__form:t2-p1____idUri__1' => 'http://type2/1',
                'form:type1__form:t1-p2__form:type2__form:t2-p1__1'          => 'sub-value1',
                'form:type1__form:t1-p2__form:type2__form:t2-p2__1'          => 'sub-value2',
                // sub entry 2 (not yet "created", therefore needs an URI)
                'form:type1__form:t1-p2__form:type2__form:t2-p1__2'          => 'sub-value3',
                'form:type1__form:t1-p2__form:type2__form:t2-p2__2'          => 'sub-value4',
                'form:t1-p2__number'                                         => '2'
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
            throw new KnorkeException('Neither field __idUri nor __uriSchema are set.');
        }

        // here we know that all data are OK

        $result = array();
        $cN = $this->commonNamespaces; // shortcut for easier usage later on

        $rootTypeBlank = $this->dataBlankHelper->load($formInput['__type']);

        // check if its an existing resource
        if (isset($formInput['__idUri'])) {
            $rootElementUri = $formInput['__idUri'];
        // if not, generate a new URI
        } else {
            $rootElementUri = $this->buildRootElementUriByUriSchema($formInput['__uriSchema'], $formInput);
        }

        // go through all required properties of the root type
        foreach ($rootTypeBlank['kno:has-property'] as $property) {
            /*
             * direct hit for key value pair
             */
            if (isset($formInput[$property['_idUri']])) {
                $result[] = $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode($cN->extendUri($rootElementUri)),
                    $this->nodeFactory->createNamedNode($cN->extendUri($property['_idUri'])),
                    $this->getNodeForGivenValue($formInput[$property['_idUri']])
                );

            /*
             * check for ..._number field which has to indicate that there is a list of sub entries
             */
            } elseif (isset($formInput[$formInput['__type'] .'__'. $property['_idUri'].'__number'])) {
                /*
                 * we assume there are sub entries for $property['_idUri'] available.
                 * these entries are of type $property['kno:restriction-reference-is-of-type']['_idUri'].
                 */

                // get all has-property entry for the referenced instance of type
                $referencedTypeBlank = $this->dataBlankHelper->load(
                    $property['kno:restriction-reference-is-of-type']['_idUri'],
                    array('use_prefixed_predicates' => true, 'use_prefixed_objects' => true,)
                );

                // first part of the subKey which identifies sub elements
                $subKey = $formInput['__type']                                                  // form:type1
                    .'__' . $property['_idUri']                                                 // form:t1-p2
                    .'__'. $this->commonNamespaces->shortenUri($referencedTypeBlank['_idUri']); // form:type2

                $entryNumber = $formInput[$formInput['__type'] .'__'. $property['_idUri'].'__number'];
                /*
                 * handle entries like
                 *      ...
                 *      'form:type1__form:t1-p2__form:type2__form:t2-p1__1' => 'sub-value1',
                 *      ...
                 */
                for ($subEntryIndex = 0; $subEntryIndex < $entryNumber; ++$subEntryIndex) {
                    /*
                     * connect root resource with this sub element
                     */
                    // sub element has an URI already
                    if (isset($formInput[$subKey .'____idUri__'. $subEntryIndex])) {
                        $result[] = $this->statementFactory->createStatement(
                           $this->nodeFactory->createNamedNode($cN->extendUri($rootElementUri)),
                           $this->nodeFactory->createNamedNode($cN->extendUri($property['_idUri'])),
                           $this->nodeFactory->createNamedNode($cN->extendUri($formInput[$subKey .'____idUri__'. $subEntryIndex]))
                        );
                        $subElementUri = $cN->extendUri($formInput[$subKey .'____idUri__'. $subEntryIndex]);
                    // generate new URI for sub element
                    } else {
                        // assume __uriSchema key is set
                        $subElementUri = $cN->extendUri($this->buildSubElementUriByUriSchema(
                            $formInput[$property['_idUri'] . '__uriSchema'],
                            $formInput,
                            $formInput['__type'],
                            $property['_idUri'],
                            $this->commonNamespaces->shortenUri($referencedTypeBlank['_idUri']),
                            $subEntryIndex,
                            $rootElementUri
                        ));

                        $result[] = $this->statementFactory->createStatement(
                           $this->nodeFactory->createNamedNode($cN->extendUri($rootElementUri)),
                           $this->nodeFactory->createNamedNode($cN->extendUri($property['_idUri'])),
                           $this->nodeFactory->createNamedNode($subElementUri)
                        );
                    }

                    // add rdf:type relation
                    $result[] = $this->statementFactory->createStatement(
                       $this->nodeFactory->createNamedNode($subElementUri),
                       $this->nodeFactory->createNamedNode($cN->extendUri('rdf:type')),
                       $this->nodeFactory->createNamedNode($cN->extendUri($formInput[$property['_idUri'] .'__type']))
                    );

                    foreach ($referencedTypeBlank['kno:has-property'] as $subProperty) {
                        $subPropertyUri = isset($subProperty['_idUri']) ? $subProperty['_idUri'] : $subProperty;

                        $result[] = $this->statementFactory->createStatement(
                            $this->nodeFactory->createNamedNode($subElementUri),
                            $this->nodeFactory->createNamedNode($cN->extendUri($subPropertyUri)),
                            $this->getNodeForGivenValue(
                                $formInput[$subKey .'__' . $subPropertyUri .'__'. $subEntryIndex]
                            )
                        );
                    }
                }
            }
        }

        return $result;
    }
}

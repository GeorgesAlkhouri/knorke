<?php

namespace Knorke\Form;

/**
 * Extends HtmlGenerator to support Twig specific elements.
 */
class TwigHtmlGenerator extends HtmlGenerator
{
    protected function getElementType(string $formArrayElement) : string
    {
        $types = array(
            '{% if'     => 'twig-if-open',
            '{% endif'  => 'twig-if-close',
            '{% for'    => 'twig-for-open',
            '{% endfor' => 'twig-for-close',
        );

        foreach ($types as $htmlFragment => $type) {
            if (false !== strpos(substr($formArrayElement, 0, 9), $htmlFragment)) {
                return $type;
            }
        }

        // no Twig element, so call parents version
        return parent::getElementType($formArrayElement);
    }

    /**
     * Is element a closing one? Like </div>.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isClosingElement(string $formArrayElement) : bool
    {
        if ($this->isTwigElement($formArrayElement)) {
            $type = $this->getElementType($formArrayElement);

            return 'twig-if-close' == $type
                || 'twig-for-close' == $type;
        } else {
            return parent::isClosingElement($formArrayElement);
        }
    }

    /**
     * Is element an opening one? Like <div>.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isOpeningElement(string $formArrayElement) : bool
    {
        if ($this->isTwigElement($formArrayElement)) {
            $type = $this->getElementType($formArrayElement);

            return 'twig-if-open' == $type
                || 'twig-for-open' == $type;
        } else {
            return parent::isOpeningElement($formArrayElement);
        }
    }

    /**
     * Is element a surrounding one? Like a div or form.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isSurroundingElement(string $formArrayElement) : bool
    {
        if ($this->isTwigElement($formArrayElement)) {
            $type = $this->getElementType($formArrayElement);

            return 'twig-if-open' == $type
                || 'twig-if-close' == $type
                || 'twig-for-open' == $type
                || 'twig-for-close' == $type;
        } else {
            return parent::isSurroundingElement($formArrayElement);
        }
    }

    /**
     * Is element a Twig element? Like {% if ... %}
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isTwigElement(string $formArrayElement) : bool
    {
        return false !== strpos(substr($formArrayElement, 0, 9), '{% if')
            || false !== strpos(substr($formArrayElement, 0, 9), '{% endif')
            || false !== strpos(substr($formArrayElement, 0, 9), '{% for')
            || false !== strpos(substr($formArrayElement, 0, 9), '{% endfor');
    }
}

<?php

namespace Knorke\Form;

use Knorke\Exception\KnorkeException;

/**
 * The purpose of this class is to transform Form arrays into ready to use HTML code.
 */
class HtmlGenerator
{
    protected $tabLengthInWhitespaces = 0;
    protected $whitespaces = '';

    /**
     * @param int $tabLengthInWhitespaces Optional, default is 4
     */
    public function __construct(int $tabLengthInWhitespaces = 4)
    {
        $this->tabLengthInWhitespaces = $tabLengthInWhitespaces;
        for ($i = 0; $i < $this->tabLengthInWhitespaces; $i++) {
            $this->whitespaces .= ' ';
        }
    }

    /**
     * Generates whitespaces based on a given $level. One level is 4 indentations (4 whitespaces).
     *
     * @param int $level Controls the number of whitespaces generated.
     * @return string Generated white spaces, based on given $level.
     */
    protected function genWSpaces(int $level = 0)
    {
        $spaces = '';
        for ($i = 0; $i < $level; ++$i) {
            $spaces .= $this->whitespaces;
        }

        return $spaces;
    }

    /**
     * @param string $formArrayElement
     * @return string Found type.
     */
    protected function getElementType(string $formArrayElement) : string
    {
        $types = array(
            '<div'      => 'div-open',
            '</div>'    => 'div-close',
            '<form'     => 'form-open',
            '</form>'   => 'form-close',
            '<span'     => 'span-open',
            '</span>'   => 'span-close',
            '<select'   => 'select-open',
            '</select>' => 'select-close',
        );

        foreach ($types as $htmlFragment => $type) {
            if (false !== strpos($formArrayElement, $htmlFragment)) {
                return $type;
            }
        }

        return 'mixed';
    }

    /**
     * Is element a closing one? Like </div>.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isClosingElement(string $formArrayElement) : bool
    {
        $type = $this->getElementType($formArrayElement);

        return 'form-close' == $type
            || 'div-close'  == $type
            || 'span-close' == $type
            || 'select-close' == $type;
    }

    /**
     * Is element an opening one? Like <div>.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isOpeningElement(string $formArrayElement) : bool
    {
        $type = $this->getElementType($formArrayElement);

        return 'form-open' == $type
            || 'div-open'  == $type
            || 'span-open' == $type
            || 'select-open' == $type;
    }

    /**
     * Is element a surrounding one? Like a div or form.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isSurroundingElement(string $formArrayElement) : bool
    {
        return $this->isOpeningElement($formArrayElement)
            || $this->isClosingElement($formArrayElement);
    }

    /**
     * @param array $formArray
     * @param int $level
     */
    public function transformFormArrayToCoolHtml(array $formArray, int $level = 0) : string
    {
        $html = '';
        $tmpLvl = $level;

        foreach ($formArray as $element) {
            // like div, form, ..
            if ($this->isSurroundingElement($element)) {
                if ($this->isClosingElement($element)) {
                    --$tmpLvl;
                }
            }

            $html .= PHP_EOL . PHP_EOL;
            $html .= $this->genWSpaces($tmpLvl);
            $html .= $element;

            // if an opening surrounding element was pasted, increase level for the next element
            if ($this->isOpeningElement($element)) {
                ++$tmpLvl;
            }
        }

        if ($tmpLvl !== $level) {
            throw new KnorkeException(
                'Given $level and $tmpLvl should not be differ: '. $level . ' != ' . $tmpLvl
            );
        }

        return $html;
    }
}

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

    public function __construct(int $tabLengthInWhitespaces = 4)
    {
        $this->tabLengthInWhitespaces = $tabLengthInWhitespaces;
        for ($i = 0; $i < $this->tabLengthInWhitespaces; $i++) {
            $this->whitespaces .= ' ';
        }
    }

    /**
     * @param int $level Controls the number of whitespaces generated.
     */
    protected function genWSpaces(int $level = 0)
    {
        $spaces = '';
        for ($i = 0; $i < $level; ++$i) {
            $spaces .= $this->whitespaces;
        }

        return $spaces;
    }

    protected function getElementType(string $formArrayElement) : string
    {
        /*
         * forms
         */
        if       (false !== strpos($formArrayElement, '<form')) {
            return 'form-open';
        } elseif (false !== strpos($formArrayElement, '</form>')) {
            return 'form-close';
        /*
         * div
         */
        } elseif (false !== strpos($formArrayElement, '<div')) {
            return 'div-open';
        } elseif (false !== strpos($formArrayElement, '</div>')) {
            return 'div-close';
        /*
         * input
         */
        } elseif (false !== strpos($formArrayElement, '<input')) {
            return 'input';

        } else {
            return 'mixed';
        }
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

        return false !== strpos($type, 'form-close')
            || false !== strpos($type, 'div-close');
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

        return false !== strpos($type, 'form-open')
            || false !== strpos($type, 'div-open');
    }

    /**
     * Is element a surrounding one? Like a div or form.
     *
     * @param string $formArrayElement
     * @return bool
     */
    protected function isSurroundingElement(string $formArrayElement) : bool
    {
        $type = $this->getElementType($formArrayElement);

        return false !== strpos($type, 'form')
            || false !== strpos($type, 'div');
    }

    /**
     * @param array $formArray
     * @param int $level
     */
    public function transformFormArrayToCoolHtml(array $formArray, int $level = 0) : string
    {
        $html = '';
        $tmpLvl = $level;

        foreach ($formArray as $key => $element) {
            // like div, form, ..
            if ($this->isSurroundingElement($element)) {
                if ($this->isClosingElement($element)) {
                    --$tmpLvl;
                }
            }

            $html .= PHP_EOL . $this->genWSpaces($tmpLvl);
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

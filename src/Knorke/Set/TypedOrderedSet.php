<?php

namespace Knorke\Set;

use Knorke\Exception\SetException;

class TypedOrderedSet extends OrderedSet
{
    /**
     * @var array
     */
    protected $typeInformation;

    /**
     * @param array $startSet
     * @param array $typeInformation
     * @throws SetException if element to add is not of given type
     */
    public function __construct(array $startSet, array $typeInformation)
    {
        if (0 == count($typeInformation)) {
            throw new SetException('No type information given.');
        } else {
            $this->typeInformation = $typeInformation;
        }

        // sort by key
        ksort($startSet);
        foreach ($startSet as $key => $entry) {
            $canBeAdded = false;

            /*
             * PHP context
             */
            if ('php' == $typeInformation['context']) {
                if ('string' == $typeInformation['type'] && is_string($entry)) {
                    $canBeAdded = true;
                }
            }

            if ($canBeAdded) {
                $this->add($entry);
            } else {
                $e = new SetException('Element is not of type ' . $typeInformation['type']);
                $e->setPayload($entry);
                throw $e;
            }
        }
    }

    public function getTypeInformation()
    {
        return $this->typeInformation;
    }
}

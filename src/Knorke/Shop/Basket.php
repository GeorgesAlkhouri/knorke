<?php

namespace Knorke\Shop;

/**
 * Represents a basket from the E-Commerce area. It is able to handle basket items (add, remove) and
 * manages a deposit, if neccessary.
 */
class Basket
{
    protected $items = array();

    function __construct()
    {
    }

    public function addItem()
    {
        // if item is of type knok:shop/service, handle deposit (add, update)
    }

    public function removeItem()
    {
        // if item is of type knok:shop/service, update deposit, if neccessary
    }
}

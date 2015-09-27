<?php

/**
 * This file demonstrate how basic basket handling is done by showing how to only add valid products.
 */

require __DIR__ .'/../../vendor/autoload.php';

/**
 * Start session to emulate a database
 */
session_start();
if (false == isset($_SESSION['basketItems'])) {
    $_SESSION['basketItems'] = array();
}

$exceptionMessage = '';

// if the user wants to add another product to the basket
if (isset($_REQUEST['addProduct'])) {
    /*
     * validate product information, before adding it to the basket
     */
    $productOrService = new \Knorke\ClassHandler(__DIR__ . '/../../knowledge/shop.ttl');
    try {
        $productOrService->validateData(
            $_REQUEST,
            'http://localhost/k00ni/knorke/shop/ProductOrService'
        );
    } catch(\Exception $e) {
        $exceptionMessage = $e->getMessage();
    }

    // validation ok, add product
    $_SESSION['basketItems'][$_REQUEST['knok:shop/name']] = array(
        'name' => $_REQUEST['knok:shop/name'],
        'price' => $_REQUEST['knok:shop/price']
    );

// if the user wants to remove a product from the basket
} elseif (isset($_REQUEST['removeProduct'])) {
    unset($_SESSION['basketItems'][$_REQUEST['removeProduct']]);
}

/**
 * Output template
 */
$loader = new Twig_Loader_Filesystem(__DIR__);
$twig = new Twig_Environment($loader);
$template = $twig->loadTemplate('basket-basic.html');
echo $template->render(array(
    'basketItems' => $_SESSION['basketItems'],
    'exceptionMessage' => $exceptionMessage
));

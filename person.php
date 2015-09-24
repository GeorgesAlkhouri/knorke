<?php

require 'vendor/autoload.php';

use Saft\Addition\Virtuoso\Store\Virtuoso;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Rdf\StatementIteratorFactoryImpl;
use Saft\Sparql\Query\QueryFactoryImpl;
use Saft\Sparql\Query\QueryUtils;
use Saft\Sparql\Result\ResultFactoryImpl;

$config = array(
    'dsn' => 'VOS',
    'username' => 'dba',
    'password' => 'dba'
);

$virtuoso = new Virtuoso(new NodeFactoryImpl(), new StatementFactoryImpl(), new QueryFactoryImpl(),
    new ResultFactoryImpl(), new StatementIteratorFactoryImpl(), $config
);

/**
 * Output template
 */
$loader = new Twig_Loader_Filesystem(__DIR__ . '/templates');
$twig = new Twig_Environment($loader);
$template = $twig->loadTemplate('person.tpl');
echo $template->render(array('person' => array('name' => 'Konrad')));

/**
 * Validation area
 */
if (isset($_REQUEST['haar:age'])) {
    $person = new \Haarpracht\Person($virtuoso, 'http://localhost/haarpracht/');
    $person->validateData($_REQUEST);
}

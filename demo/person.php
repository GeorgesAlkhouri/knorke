<?php

require __DIR__ .'/../vendor/autoload.php';

/**
 * Output template
 */
$loader = new Twig_Loader_Filesystem(__DIR__ . '/templates');
$twig = new Twig_Environment($loader);
$template = $twig->loadTemplate('person.tpl');
echo $template->render(array());

/**
 * Validation area
 */
if (isset($_REQUEST['knok:person/age'])) {
    $person = new \Knorke\ClassHandler(__DIR__ . '/../knowledge/person.ttl');
    $person->validateData($_REQUEST, 'http://localhost/k00ni/knorke/person/Person');
}

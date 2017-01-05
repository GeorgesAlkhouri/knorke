<?php

/**
 * Demonstrates usage of finite state machine
 */

require __DIR__ .'/../../vendor/autoload.php';

use Knorke\DataValidator\DataValidator;
use Knorke\Database\MemoryQueryHandler;
use Knorke\Exception\DataValidatorException;
use Knorke\FiniteStateMachine\Machine;
use Knorke\CommonNamespaces;

// assume we have a HTML form and it was send to the server with the following data

$dataToCheck = array(
    // prefixed URI to source file:class name/property name (we assume, that the property and class have the same prefix
    // as the class)
    'kno-person:age' => 11,
    'kno-person:firstname' => 'John',
    'kno-person:lastname' => 'Doe',
);

echo PHP_EOL . 'Example data: '. PHP_EOL;
var_dump($dataToCheck);

$commonNamespaces = new CommonNamespaces();
$commonNamespaces->add('kno-person', 'https://raw.githubusercontent.com/k00ni/knorke/master/knowledge/person.ttl#');

$dataValidator = new DataValidator($commonNamespaces);
$ontologicalModel = $dataValidator->loadOntologicalModel(
    __DIR__ . '/../../knowledge/knorke/person.nt'
);

try {
    $dataValidator->validate(
        $dataToCheck,
        $ontologicalModel,
        'https://raw.githubusercontent.com/k00ni/knorke/master/knowledge/person.ttl#Person'
    );
    /*
     * validation ok, store data

    $memory = new MemoryQueryHandler();
    $memory->transition('prepare', array(array()));
    $memory->transition('send_query', array(array('key' => '0', 'values' => $personData))); // store data
    $memory->transition('send_query', array('0')); // get data by key

    /*
     * output stored data
     *
    $memory->registerCallbackOn('handle_result', function($transitionInfo, $result) {
        echo PHP_EOL . 'handle result from the OUTSIDE'. PHP_EOL;
        var_dump($result);
    });
    $memory->transition('handle_result');
    */

} catch (DataValidatorException $e) {
    echo PHP_EOL . 'Validation Exception: ' . $e->getMessage() . PHP_EOL;
    var_dump($e->getPayload());

} catch (\Exception $e) {
    echo PHP_EOL . 'Exception: ' . $e->getMessage() . PHP_EOL;
}

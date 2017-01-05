<?php

/**
 * Demonstrates basic entity management: We assume that we received a request with data to store
 *
 * 1. validate data using Knorke's person.ttl (change values from $dataToCheck to experiment with validation errors)
 * 2. after OK validation, we store data into a memory store and get it (using a finite state machine)
 */

require __DIR__ .'/../../vendor/autoload.php';

use Knorke\Database\MemoryQueryHandler;
use Knorke\DataValidator\DataValidator;
use Knorke\Exception\DataValidatorException;
use Knorke\FiniteStateMachine\Machine;
use Knorke\CommonNamespaces;

// assume we have a HTML form and it was send to the server with the following data

$dataToCheck = array(
    // schema for keys: shorten-file-URI:property-name
    'kno-person:age' => 11,
    'kno-person:firstname' => 'John',
    'kno-person:lastname' => 'Doe',
);

echo PHP_EOL . 'Example data: '. PHP_EOL;
var_dump($dataToCheck);

$commonNamespaces = new CommonNamespaces();
$commonNamespaces->add('kno-person', 'https://raw.githubusercontent.com/k00ni/knorke/master/knowledge/person.ttl#');

$dataValidator = new DataValidator($commonNamespaces);
$dataValidator->loadOntologicalModel(__DIR__ . '/../../knowledge/knorke/person.nt');

try {
    $dataValidator->validate(
        $dataToCheck,
        'https://raw.githubusercontent.com/k00ni/knorke/master/knowledge/person.ttl#Person'
    );
    /*
     * validation ok, store data
     */
    $memory = new MemoryQueryHandler();
    $memory->transition('prepare', array(array()));
    $memory->transition('send_query', array(array('key' => '0', 'values' => $dataToCheck))); // store data
    $memory->transition('send_query', array('0')); // get data by key

    /*
     * output stored data
     */
    $memory->registerCallbackOn('handle_result', function($transitionInfo, $result) {
        echo PHP_EOL . 'handle result from the OUTSIDE'. PHP_EOL;
        var_dump($result);
    });
    $memory->transition('handle_result');

/*
 * Handle validation errors
 */
} catch (DataValidatorException $e) {
    echo PHP_EOL . 'Validation Exception: ' . $e->getMessage() . PHP_EOL;
    var_dump($e->getPayload());

} catch (\Exception $e) {
    echo PHP_EOL . 'Exception: ' . $e->getMessage() . PHP_EOL;
}

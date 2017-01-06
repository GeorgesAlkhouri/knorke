<?php

/**
 * Demonstrates usage of finite state machine
 */

require __DIR__ .'/../../vendor/autoload.php';

use Knorke\Set\OrderedSet;
use Knorke\Set\TypedOrderedSet;

$set = new OrderedSet();
$set->add('first');
$set->add('second');
$set->add('third');

foreach ($set as $key => $value) {
    echo PHP_EOL . $key . ': '. $value;
}

echo PHP_EOL;
echo PHP_EOL;

$typedSet = new TypedOrderedSet(array('s_first', 's_second', 's_third'), array('context' => 'php', 'type' => 'string'));

foreach ($typedSet as $key => $value) {
    echo PHP_EOL . $key . ': '. $value;
}

echo PHP_EOL;

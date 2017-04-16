<?php

namespace Tests\Knorke;

use PHPUnit\Framework\TestCase;
use Saft\Rdf\NodeFactoryImpl;
use Saft\Rdf\NodeUtils;
use Saft\Rdf\StatementFactoryImpl;
use Saft\Sparql\Result\SetResult;

class UnitTestCase extends TestCase
{
    /**
     * Contains an instance of the class to test.
     *
     * @var mixed
     */
    protected $fixture;

    protected $nodeFactory;
    protected $nodeUtils;
    protected $statementFactory;

    protected $testGraphUri = 'http://knorke/testgraph/';
    protected $testGraph;

    public function setUp()
    {
        parent::setUp();
        $this->nodeUtils = new NodeUtils();
        $this->nodeFactory = new NodeFactoryImpl($this->nodeUtils);
        $this->statementFactory = new StatementFactoryImpl();
        $this->testGraph = $this->nodeFactory->createNamedNode($this->testGraphUri);
    }

    /**
     * Checks two lists which implements \Iterator interface, if they contain the same Statement instances.
     * The checks will be executed using PHPUnit's assert functions.
     *
     * @param SetResult $expected
     * @param SetResult $actual
     */
    public function assertSetIteratorEquals(SetResult $expected, SetResult $actual)
    {
        $entriesToCheck = array();
        foreach ($expected as $entry) {
            // serialize entry and hash it afterwards to use it as key for $entriesToCheck array.
            // later on we only check the other list that each entry, serialized and hashed, has
            // its equal key in the list.
            // the structure of each entry is an associative array which contains Node instances.
            $entryString = '';
            foreach ($entry as $key => $nodeInstance) {
                if ($nodeInstance->isConcrete()) {
                    // build a string of all entries of $entry and generate a hash based on that later on.
                    $entryString = $nodeInstance->toNQuads();
                } else {
                    throw new \Exception('Non-concrete Node instance in SetResult instance found.');
                }
            }
            $entriesToCheck[hash('sha256', $entryString)] = false;
        }

        // contains a list of all entries, which were not found in $expected.
        $actualEntriesNotFound = array();
        $actualRealEntriesNotFound = array();
        foreach ($actual as $entry) {
            $entryString = '';
            foreach ($entry as $key => $nodeInstance) {
                if ($nodeInstance->isConcrete()) {
                    // build a string of all entries of $entry and generate a hash based on that later on.
                    $entryString = $nodeInstance->toNQuads();
                } else {
                    throw new \Exception('Non-concrete Node instance in SetResult instance found.');
                }
            }
            $entryHash = hash('sha256', $entryString);
            if (isset($entriesToCheck[$entryHash])) {
                // if entry was found, mark it.
                $entriesToCheck[$entryHash] = true;
            } else {
                // entry was not found
                $actualEntriesNotFound[] = $entryHash;
                $actualRealEntriesNotFound[] = $entry;
            }
        }
        $notCheckedEntries = array();
        // check that all entries from $expected were checked
        foreach ($entriesToCheck as $key => $value) {
            if (!$value) {
                $notCheckedEntries[] = $key;
            }
        }

        if (!empty($actualEntriesNotFound) || !empty($notCheckedEntries)) {
            $message = 'The StatementIterators are not equal.';
            if (!empty($actualEntriesNotFound)) {
                echo PHP_EOL . PHP_EOL . 'Not expected entries:' . PHP_EOL;
                print_r($actualRealEntriesNotFound);
                $message .= ' ' . count($actualEntriesNotFound) . ' Statements where not expected.';
            }
            if (!empty($notCheckedEntries)) {
                print_r($notCheckedEntries);
                $message .= ' ' . count($notCheckedEntries) . ' Statements where not present but expected.';
            }
            $this->fail($message);

        } elseif (0 == count($actualEntriesNotFound) && 0 == count($notCheckedEntries)) {
            $this->assertEquals($expected->getVariables(), $actual->getVariables());
        }
    }
}

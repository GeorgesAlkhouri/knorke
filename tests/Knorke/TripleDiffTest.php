<?php

namespace Tests\Knorke;

use Knorke\TripleDiff;

class TripleDiffTest extends UnitTestCase
{
    /**
     * Gets called before each test function gets called.
     */
    public function setUp()
    {
        parent::setUp();

        $this->fixture = new TripleDiff(
            $this->rdfHelpers,
            $this->commonNamespaces,
            $this->nodeFactory,
            $this->statementFactory
        );
    }

    protected function generate2TestSets() : array
    {
        /**
         * set1
         */
        $set1 = array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://set1/a'),
                $this->nodeFactory->createNamedNode('http://set1/b'),
                $this->nodeFactory->createNamedNode('http://set1/c')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://both/a'),
                $this->nodeFactory->createNamedNode('http://both/b'),
                $this->nodeFactory->createNamedNode('http://both/c')
            ),
        );

        /**
         * set2
         */
        $set2 = array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://set2/1'),
                $this->nodeFactory->createNamedNode('http://set2/2'),
                $this->nodeFactory->createNamedNode('http://set2/3')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://both/a'),
                $this->nodeFactory->createNamedNode('http://both/b'),
                $this->nodeFactory->createNamedNode('http://both/c')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://set2/4'),
                $this->nodeFactory->createNamedNode('http://set2/5'),
                $this->nodeFactory->createLiteral('666')
            ),
        );

        return array($set1, $set2);
    }

    /*
     * Tests for computeDiff
     */

    public function testComputeDiff()
    {
        list($set1, $set2) = $this->generate2TestSets();

        $diffArray = $this->fixture->computeDiff($set1, $set2);

        $this->assertEquals(
            array(
                // the following is only in set1
                array(
                    $this->statementFactory->createStatement(
                        $this->nodeFactory->createNamedNode('http://set1/a'),
                        $this->nodeFactory->createNamedNode('http://set1/b'),
                        $this->nodeFactory->createNamedNode('http://set1/c')
                    )
                ),
                // the following is only in set2
                array(
                    $this->statementFactory->createStatement(
                        $this->nodeFactory->createNamedNode('http://set2/1'),
                        $this->nodeFactory->createNamedNode('http://set2/2'),
                        $this->nodeFactory->createNamedNode('http://set2/3')
                    ),
                    $this->statementFactory->createStatement(
                        $this->nodeFactory->createNamedNode('http://set2/4'),
                        $this->nodeFactory->createNamedNode('http://set2/5'),
                        $this->nodeFactory->createNamedNode('http://set2/6')
                    )
                )
            ),
            $diffArray
        );
    }

    // tests how it reacts if one set is empty
    public function testComputeDiffOneSetEmpty()
    {
        list($set1, $set2ToBeIgnored) = $this->generate2TestSets();

        $diffArray = $this->fixture->computeDiff($set1, array());

        $this->assertEquals(
            array(
                // the following is only in set1
                array(
                    $this->statementFactory->createStatement(
                        $this->nodeFactory->createNamedNode('http://set1/a'),
                        $this->nodeFactory->createNamedNode('http://set1/b'),
                        $this->nodeFactory->createNamedNode('http://set1/c')
                    ),
                    $this->statementFactory->createStatement(
                        $this->nodeFactory->createNamedNode('http://both/a'),
                        $this->nodeFactory->createNamedNode('http://both/b'),
                        $this->nodeFactory->createNamedNode('http://both/c')
                    ),
                ),
                // set2 is empty
                array()
            ),
            $diffArray
        );
    }
}

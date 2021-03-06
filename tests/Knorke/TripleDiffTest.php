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
            $this->statementFactory,
            $this->store
        );

        // hint: for each test the WHOLE db gets ereased. The graph behind $this->testGraph gets
        //       re-created for each test function freshly. For more info look into tests/Knorke/UnitTestCase.php.
    }

    protected function generate2TestQuadSets() : array
    {
        /**
         * set1
         */
        $set1 = array(
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://set1/a'),
                $this->nodeFactory->createNamedNode('http://set1/b'),
                $this->nodeFactory->createNamedNode('http://set1/c'),
                $this->nodeFactory->createNamedNode('http://graph1/')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://both/a'),
                $this->nodeFactory->createNamedNode('http://both/b'),
                $this->nodeFactory->createNamedNode('http://both/c'),
                $this->nodeFactory->createNamedNode('http://graph1/')
            ),
        );

        /**
         * set2
         */
        $set2 = array(
          $this->statementFactory->createStatement(
              $this->nodeFactory->createNamedNode('http://set1/a'),
              $this->nodeFactory->createNamedNode('http://set1/b'),
              $this->nodeFactory->createNamedNode('http://set1/c'),
              $this->nodeFactory->createNamedNode('http://graph2/')
          ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://both/a'),
                $this->nodeFactory->createNamedNode('http://both/b'),
                $this->nodeFactory->createNamedNode('http://both/c'),
                $this->nodeFactory->createNamedNode('http://graph1/')
            ),
            $this->statementFactory->createStatement(
                $this->nodeFactory->createNamedNode('http://set2/4'),
                $this->nodeFactory->createNamedNode('http://set2/5'),
                $this->nodeFactory->createLiteral('666'),
                $this->nodeFactory->createNamedNode('http://graph2/')
            ),
        );

        return array($set1, $set2);
    }

    /**
     * Generates two test sets of Statement instances.
     *
     * @return array Array of Statement instances.
     */
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
     * Tests for computeDiffForTwoGraphs
     */

    public function testComputeDiffForTwoGraphs()
    {
        /*
         * generate test data
         */
        list($set1, $set2) = $this->generate2TestSets();

        // fill graph 1
        $this->store->addStatements($set1, $this->testGraph);

        // fill graph 2
        $graphToCheckAgainst = $this->nodeFactory->createNamedNode($this->testGraph->getUri() . '2');

        $this->store->createGraph($graphToCheckAgainst); // create second graph, because only the first one is available
        $this->store->addStatements($set2, $graphToCheckAgainst); // add test data to the graph

        /*
         * compute diff
         */
        $diffArray = $this->fixture->computeDiffForTwoGraphs(
            $this->testGraph->getUri(), // graph 1
            $graphToCheckAgainst        // graph 2
        );

        // check
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
                        $this->nodeFactory->createLiteral('666')
                    )
                )
            ),
            $diffArray
        );
    }

    /*
     * Tests for computeDiffForTwoTripleSets
     */

    public function testComputeDiffForTwoTripleSets()
    {
        list($set1, $set2) = $this->generate2TestSets();


        $diffArray = $this->fixture->computeDiffForTwoTripleSets($set1, $set2);

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
                        $this->nodeFactory->createLiteral('666')
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

        $diffArray = $this->fixture->computeDiffForTwoTripleSets($set1, array());

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

    public function testComputeDiffTwoSetEmpty()
    {
        list($set1ToBeIgnored, $set2ToBeIgnored) = $this->generate2TestSets();

        $diffArray = $this->fixture->computeDiffForTwoTripleSets(array(), array());

        $this->assertEquals(
            array(
                array(),
                array()
            ),
            $diffArray
        );
    }

    public function testDiffWithTwoQuadSets()
    {
        list($set1, $set2) = $this->generate2TestQuadSets();

        $diffArray = $this->fixture->computeDiffForTwoTripleSets($set1, $set2, true);

        $this->assertEquals(
          array(
              array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://set1/a'),
                    $this->nodeFactory->createNamedNode('http://set1/b'),
                    $this->nodeFactory->createNamedNode('http://set1/c'),
                    $this->nodeFactory->createNamedNode('http://graph1/')
                )
              ),
              array(
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://set1/a'),
                    $this->nodeFactory->createNamedNode('http://set1/b'),
                    $this->nodeFactory->createNamedNode('http://set1/c'),
                    $this->nodeFactory->createNamedNode('http://graph2/')
                  ),
                $this->statementFactory->createStatement(
                    $this->nodeFactory->createNamedNode('http://set2/4'),
                    $this->nodeFactory->createNamedNode('http://set2/5'),
                    $this->nodeFactory->createLiteral('666'),
                    $this->nodeFactory->createNamedNode('http://graph2/')
                )
              )
          ),
          $diffArray
      );
    }

    public function testDiffWithOneEmptyQuadSet()
    {
      list($set1, $ignore) = $this->generate2TestQuadSets();

      $diffArray = $this->fixture->computeDiffForTwoTripleSets($set1, array());

      $this->assertEquals(
        array(
            array(
              $this->statementFactory->createStatement(
                  $this->nodeFactory->createNamedNode('http://set1/a'),
                  $this->nodeFactory->createNamedNode('http://set1/b'),
                  $this->nodeFactory->createNamedNode('http://set1/c'),
                  $this->nodeFactory->createNamedNode('http://graph1/')
              ),

              $this->statementFactory->createStatement(
                  $this->nodeFactory->createNamedNode('http://both/a'),
                  $this->nodeFactory->createNamedNode('http://both/b'),
                  $this->nodeFactory->createNamedNode('http://both/c'),
                  $this->nodeFactory->createNamedNode('http://graph1/')
              )
            ),
            array()
        ),
        $diffArray
      );
    }

}

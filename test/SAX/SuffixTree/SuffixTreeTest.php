<?php
include("../../../lib/SAX/SuffixTree/SuffixTree.php");

class SuffixTreeTest extends PHPUnit_Framework_TestCase {

    private $suffixTree;

    private $stringToTest;

    public function setUp() {
        $this->stringToTest = "mississippi";
        $this->suffixTree   = new SuffixTree($this->stringToTest);
    }

    public function testHasSubstring() {
        for ($i=1; $i < strlen( $this->stringToTest ) + 1; $i++) {
            for ($j=0; $j < strlen( $this->stringToTest ) - $i + 1; $j++) { 
                $substring = substr($this->stringToTest, $j, $i);
                $this->assertEquals(1, $this->suffixTree->hasSubstring($substring));
            }
        }
    }
}


?>
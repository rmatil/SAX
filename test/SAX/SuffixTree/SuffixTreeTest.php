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

    public function testGetOccurence() {
        $this->assertEquals(1, $this->suffixTree->getOccurence("mississippi"));
        $this->assertEquals(2, $this->suffixTree->getOccurence("issi"));
        $this->assertEquals(1, $this->suffixTree->getOccurence("pi"));
        $this->assertEquals(4, $this->suffixTree->getOccurence("i"));
        $this->assertEquals(2, $this->suffixTree->getOccurence("ss"));
    }
}


?>
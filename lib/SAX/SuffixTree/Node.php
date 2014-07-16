<?php

class Node {

    /**
     * Array of child nodes
     * @var array
     */
    public $next;

    /**
     * Start index of the string 
     * represented of this node in SuffixTree->text
     * @var integer
     */
    public $start;

    /**
     * End index of the string 
     * represented of this node in SuffixTree->text
     * @var integer
     */
    public $end;

    /**
     * Suffixlink
     * @var integer
     */
    public $link;

    /**
     * Index of this node in SuffixTree->text
     * @var integer
     */
    public $nodeIndex;

    /**
     * Instantiate a new node. 
     * @param integer $pStart     Start index of substring in SuffixTree->text
     * @param integer $pEnd       End index of substring in SuffixTree->text
     * @param integer $pNodeIndex Index of this node in SuffixTree->nodes
     */
    public function __construct($pStart, $pEnd, $pNodeIndex) {
        $this->next         = array();
        $this->start        = $pStart;
        $this->end          = $pEnd;
        $this->link         = 0;
        $this->nodeIndex    = $pNodeIndex;
    }

    public function edgeLength($pCurrentPosition) {
        return min( $this->end, $pCurrentPosition + 1 ) - $this->start;
    }
}

?>
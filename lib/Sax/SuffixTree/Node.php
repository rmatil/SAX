<?php

namespace Sax\SuffixTree;

/**
 * Represents a node in the suffix tree.
 *
 * @author Raphael Matile <raphael.matile@gmail.com>
 */
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
     * Surprise value of the string represented by this node
     * in connection with a given reference tree.
     * @var float
     */
    public $surpriseValue;

    /**
     * Instantiate a new node. 
     * @param integer $pStart     Start index of substring in SuffixTree->text
     * @param integer $pEnd       End index of substring in SuffixTree->text
     * @param integer $pNodeIndex Index of this node in SuffixTree->nodes
     */
    public function __construct( $pStart, $pEnd, $pNodeIndex ) {
        $this->next         = array();
        $this->start        = $pStart;
        $this->end          = $pEnd;
        $this->link         = 0;
        $this->nodeIndex    = $pNodeIndex;
    }

    /**
     * Returns the length of the edge between the current position and 
     * the start index of the word represented by this node.
     * 
     * @param  integer $pCurrentPosition Current position in the tree
     * @return integer                   Length of the edge
     */
    public function edgeLength( $pCurrentPosition ) {
        return min( $this->end, $pCurrentPosition + 1 ) - $this->start;
    }
}

?>
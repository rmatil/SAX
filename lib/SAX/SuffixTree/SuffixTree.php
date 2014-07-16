<?php
include("Node.php");

class SuffixTree {

    /**
     * All nodes of the tree
     * @var array
     */
    public $nodes;

    /**
     * Representing infinitive
     * @var integer
     */
    private $upperBound;

    /**
     * String on which this tree is built
     * @var string
     */
    private $text;

    /**
     * Index of root node
     * @var integer
     */
    private $root;

    /**
     * Current position in $text
     * @var integer
     */
    private $position;

    /**
     * Index of current node in $nodes
     * @var integer
     */
    private $currentNode;

    /**
     * If a node needs a suffix link
     * @var integer
     */
    private $needSuffixLink;

    /**
     * Size of remainder
     * @var integer
     */
    private $remainder;    

    /**
     * Index of the node from where 
     * a new suffix gets inserted
     * @var integer
     */
    private $activeNode;

    /**
     * Length of active edge
     * @var integer
     */
    private $activeLength;

    /**
     * Index in $text where current active
     * @var integer
     */
    private $activeEdge;


    /**
     * Instantiate new SuffixTree.
     * 
     * @param string $pString String of which the suffix tree should be built
     */
    public function __construct( $pString ) {
        $this->upperBound       = PHP_INT_MAX / 2;
        $this->needSuffixLink   = -1;
        $this->remainder        = 0;
        $this->nodes            = array();
        $this->root             = $this->newNode( -1, -1, NULL );
        $this->activeNode       = $this->root;
        $this->activeEdge       = 0;
        $this->activeLength     = 0;
        $this->position         = -1;
        $this->text             = "";

        $this->build($pString);
    }

    private function build( $pString ) {
        for ($i=0; $i < strlen( $pString ); $i++) { 
            $this->addChar( $pString[$i] );
        }
    }

    private function addSuffixLink( $pNode ) {
        if ($this->needSuffixLink > 0) {
            $this->nodes[$this->needSuffixLink]->link = $pNode;
        }
        $this->needSuffixLink = $pNode;
    }

    private function getActiveEdge() {
        return $this->text[$this->activeEdge];
    }

    private function walkDown($pNext) {
        if ($this->activeLength >= $this->nodes[$pNext]->edgeLength($this->position)) {
            $this->activeEdge   += $this->nodes[$pNext]->edgeLength($this->position);
            $this->activeLength -= $this->nodes[$pNext]->edgeLength($this->position);
            $this->activeNode   = $pNext;
            return true;
        }
        return false;
    }

    private function newNode($pStart, $pEnd) {
        $this->nodes[++$this->currentNode] = new Node($pStart, $pEnd, $this->currentNode);
        return $this->currentNode;
    }

    private function addChar($pChar) {
        $this->text[++$this->position]  = $pChar;
        $this->needSuffixLink           = -1;
        $this->remainder++;

        while( $this->remainder > 0 ) {
            if ( $this->activeLength === 0 ) {
                $this->activeEdge = $this->position;
            }
            
            if ( !array_key_exists( $this->getActiveEdge(), $this->nodes[$this->activeNode]->next ) ) {
                // suffix does not exist in the parent
                $leaf = $this->newNode($this->position, $this->upperBound);
                $this->nodes[$this->activeNode]->next[$this->getActiveEdge()] = $leaf;
                $this->addSuffixLink($this->activeNode);
            } else {
                $next = $this->nodes[$this->activeNode]->next[$this->getActiveEdge()];
                if ( $this->walkDown( $next ) ) {
                    continue;
                }
                if ( $this->text[$this->nodes[$next]->start + $this->activeLength] === $pChar ) {
                    $this->activeLength++;
                    $this->addSuffixLink($this->activeNode);
                    break;
                }
                $split = $this->newNode($this->nodes[$next]->start, $this->nodes[$next]->start + $this->activeLength);
                $this->nodes[$this->activeNode]->next[$this->getActiveEdge()] = $split;
                $leaf = $this->newNode($this->position, $this->upperBound);

                $this->nodes[$split]->next[$pChar] = $leaf;
                $this->nodes[$next]->start += $this->activeLength;
                $this->nodes[$split]->next[$this->text[$this->nodes[$next]->start]] = $next;
                $this->addSuffixLink($split);
            }
            $this->remainder--;

            if ( $this->activeNode == $this->root && $this->activeLength > 0 ) {
                $this->activeLength--;
                $this->activeEdge = $this->position - $this->remainder + 1;
            } else {
                if ( $this->nodes[$this->activeNode]->link > 0 ) {
                    $this->activeNode = $this->nodes[$this->activeNode]->link;
                } else {
                    $this->activeNode = $this->root;
                }
            }
        }
    }

    private function findSubstring(Node $pNode, $pSubstring) {
        $length = $pNode->end - $pNode->start;
        $text   = implode('', $this->text);
        if ( ($pNode->start != -1 && $pNode->end != -1 ) ) { 
            // node is not the root node
            if ( substr( $text , $pNode->start, $length ) === $pSubstring ) { 
                // found substring
                return 1;
            } elseif ( strlen( $pSubstring ) < $length ) {
                // string is only substring of string represented by pNode
                $substringLength = strlen($pSubstring);
                for ($i=0; $i < strlen($text) - $substringLength; $i++) { 
                    if ( substr( $text, $pNode->start + $i, $substringLength ) === $pSubstring) {
                        return 1;
                    }
                }
            }
        }

        // else check childnodes for substring of pSubstring
        // substring is pSubstring without the letters represented by pNode
        $ret = -1;
        foreach ($pNode->next as $childKey => $childValue) {
            if ($ret == 1) {
                // we found the substring in another path already
                return 1;
            }
            // check begin of substring with begin 
            // of string represented by this node
            if ($childKey !== $pSubstring[0]) {
                // substring does not start with 
                // the same letter -> check other paths
                continue;
            }
            // found a child
            // starting with the same letter -> check child
            $childNode          = $this->nodes[$childValue];
            $childNodeLength    = $childNode->end - $childNode->start;

            // pop letter of pSubstring and check with children
            if (strlen($pSubstring) <= $childNodeLength) {
                return 1;
            }

            $pSubstring = substr($pSubstring, $childNodeLength, strlen($pSubstring));
            $ret = $this->findSubstring( $childNode,  $pSubstring );
        }
        return $ret;
    }

    /**
     * Scan tree for occurences of the given substring.
     * 
     * @param  string  $pSubstring String to check whether it is contained or not
     * @return integer             Returns 1 if found, -1 if not
     */
    public function hasSubstring($pSubstring) {
        if (strlen($pSubstring) < 1) {
            return -1;
        }
        return $this->findSubstring($this->nodes[$this->root], $pSubstring);
    }

    public function __toString() {
        $s = "Start \tEnd \tLink \tidx \tchildren \n";
        foreach ($this->nodes as $node) {
            $substrings = "";
            $keys = array_keys($node->next);
            
            foreach ($keys as $key) {
                $substrings .= $key." => ".$node->next[$key]."\n";
                $substrings .= "\t \t \t \t";
            }

            $end = $node->end;
            if ( $node->end > 1000000)  {
                $end = "inf";
            }

            $s .= sprintf("%s \t%s \t%s \t%s \t%s \n", $node->start, $end, $node->link, $node->nodeIndex, $substrings);
        }
        return $s;
    }

}
?>
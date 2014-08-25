<?php
namespace Sax\SuffixTree;

include("Node.php");

/**
 * Handles creation of the suffix tree.
 * Based on the implementation of makagonov on {@link http://stackoverflow.com/a/14580102}
 * 
 * @author Raphael Matile <raphael.matile@gmail.com>
 */
class SuffixTree {

    /**
     * All nodes of the tree
     * @var array
     */
    public $nodes;

    /**
     * Index of root node
     * @var integer
     */
    public $root;

    /**
     * String on which this tree is built
     * @var string
     */
    public $text;

    /**
     * Representing infinitive
     * @var integer
     */
    private $upperBound;

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

    /**
     * Builds the suffix tree off the given string
     * @param  string $pString String to build the suffix tree of
     */
    private function build( $pString ) {
        for ( $i=0; $i < strlen( $pString ); $i++ ) { 
            $this->addChar( $pString[$i] );
        }
    }

    /**
     * Adds the given node as target of the 
     * current suffix link
     * 
     * @param integer $pNode Index of target node of the suffix link
     */
    private function addSuffixLink( $pNode ) {
        if ( $this->needSuffixLink > 0 ) {
            $this->nodes[$this->needSuffixLink]->link = $pNode;
        }
        $this->needSuffixLink = $pNode;
    }

    /**
     * Returns the first character of the active edge
     * @return string The first character on the active edge
     */
    private function getActiveEdge() {
        return $this->text[$this->activeEdge];
    }

    /**
     * Update active triple consisting of ( activeEdge, activeLength, activeNode )
     * 
     * @param  integer $pNext Index of node on which to walk down.
     * @return boolean        true, if walked down, otherwise false
     */
    private function walkDown( $pNext ) {
        if ($this->activeLength >= $this->nodes[$pNext]->edgeLength( $this->position ) ) {
            $this->activeEdge   += $this->nodes[$pNext]->edgeLength( $this->position );
            $this->activeLength -= $this->nodes[$pNext]->edgeLength( $this->position );
            $this->activeNode   = $pNext;
            return true;
        }

        return false;
    }

    /**
     * Creates as new node with given start and end indexes. Increases
     * the current node by one.
     * @param  integer $pStart Start index of node in string represented
     *                         by this tree
     * @param  integer $pEnd   End index of node in string represented
     *                         by this tree
     * @return integer         Index of the current node
     */
    private function newNode( $pStart, $pEnd ) {
        $this->nodes[++$this->currentNode] = new Node( $pStart, $pEnd, $this->currentNode );
        
        return $this->currentNode;
    }

    /**
     * Adds a single character to the suffix tree. 
     * Updates edges as well as nodes
     * 
     * @param string $pChar A single character
     */
    private function addChar( $pChar ) {
        $this->text[++$this->position]  = $pChar;
        $this->needSuffixLink           = -1;
        $this->remainder++;

        while( $this->remainder > 0 ) {
            if ( $this->activeLength === 0 ) {
                $this->activeEdge = $this->position;
            }
            
            if ( !array_key_exists( $this->getActiveEdge(), $this->nodes[$this->activeNode]->next ) ) {
                // suffix does not exist in the parent
                $leaf = $this->newNode( $this->position, $this->upperBound );
                $this->nodes[$this->activeNode]->next[$this->getActiveEdge()] = $leaf;
                $this->addSuffixLink( $this->activeNode );
            } else {
                $next = $this->nodes[$this->activeNode]->next[$this->getActiveEdge()];
                if ( $this->walkDown( $next ) ) {
                    continue;
                }
                if ( $this->text[$this->nodes[$next]->start + $this->activeLength] === $pChar ) {
                    $this->activeLength++;
                    $this->addSuffixLink( $this->activeNode );
                    break;
                }
                $split = $this->newNode( $this->nodes[$next]->start, $this->nodes[$next]->start + $this->activeLength );
                $this->nodes[$this->activeNode]->next[$this->getActiveEdge()] = $split;
                $leaf = $this->newNode( $this->position, $this->upperBound );

                $this->nodes[$split]->next[$pChar] = $leaf;
                $this->nodes[$next]->start += $this->activeLength;
                $this->nodes[$split]->next[$this->text[$this->nodes[$next]->start]] = $next;
                $this->addSuffixLink( $split );
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

    /**
     * Get surprise value of a substring of this tree
     * 
     * @param  string $pSubstring Substring to get surprise value off
     * @return float              Surprise value for the given substring
     */
    public function getSurpriseValue( $pSubstring ) {
        return $this->findSurpriseValue( $this->nodes[$this->root], $pSubstring );
    }

    /**
     * Returns an array containing the surprise values of 
     * each node of this tree.
     * 
     * @return array The array
     */
    public function getAllSurpriseValues() {
        $allSurprises = array();
        foreach ( $this->nodes as $node ) {
            if ( $node->start != -1 && $node->end != -1 ) {
                $allSurprises[] = $node->surpriseValue;
            }
        }
        return $allSurprises;
    }

    /**
     * Tries to find the surprise value of the given substring in the given node.
     * If not successfull for the given string and a child node starts with
     * the first letter of the given string, it will try its corresponding child.
     * Used in a recursive manner with given node representing the root node
     * at the begin
     * 
     * @param  Node   $pNode      Node on which to try to find the given substring
     * @param  string $pSubstring String to get occurences of
     * @return integer            Amount of surprise for the given substring
     */
    private function findSurpriseValue( Node $pNode, $pSubstring ) {
        $length = $pNode->end - $pNode->start;
        $text   = implode('', $this->text);
        if ( ($pNode->start != -1 && $pNode->end != -1 ) ) { 
            // node is not the root node
            if ( substr( $text , $pNode->start, $length ) === $pSubstring ) { 
                // found substring
                return $pNode->surpriseValue;
            } elseif ( strlen( $pSubstring ) < $length && strlen( $pSubstring ) > 0 ) {
                // string is only substring of string represented by pNode
                $substringLength = strlen($pSubstring);
                for ( $i=0; $i < strlen($text) - $substringLength; $i++ ) { 
                    if ( substr( $text, $pNode->start + $i, $substringLength ) === $pSubstring) {
                        return $pNode->surpriseValue;
                    }
                }
            }

            // shorten
            $pSubstring = substr($pSubstring, $length, strlen($pSubstring));
        }

        // else check childnodes for substring of pSubstring
        // substring is pSubstring without the letters represented by pNode
        $ret = $this->upperBound;
        foreach ( $pNode->next as $childKey => $childValue ) {
            if ($ret != $this->upperBound) {
                // we found the substring in another path already
                return $ret;
            }
            // check begin of substring with begin 
            // of string represented by this node
            if ($childKey !== $pSubstring[0]) {
                // substring does not start with 
                // the same letter -> check other paths
                continue;
            }
            // found a child starting with the same letter -> check child
            $childNode          = $this->nodes[$childValue];
            $childNodeLength    = $childNode->end - $childNode->start;

            $ret = $this->findSurpriseValue( $childNode,  $pSubstring );
        }

        return $ret;

    }

    /**
     * Tries to find parts of the given substring on the edge to the given node.
     * If successfull, the part of the given node is cutted of the substring. 
     * Children ( if any ), will continue finding the rest of the substring.
     * Used in a recursive manner with given node representing the root node
     * at the begin
     * 
     * @param  Node   $pNode      Node on which to try to find the given substring
     * @param  string $pSubstring String to get occurences of
     * @return integer            Amount of surprise for the given substring
     */
    private function findSubstring( Node $pNode, $pSubstring ) {
        $length = $pNode->end - $pNode->start;
        $text   = implode('', $this->text);
        if ( $pNode->start != -1 && $pNode->end != -1 ) { 
            // node is not the root node
            if ( substr( $text , $pNode->start, $length ) === $pSubstring ) { 
                // found substring
                return 1;
            } elseif ( strlen( $pSubstring ) < $length && strlen( $pSubstring ) > 0 ) {
                // string is only substring of string represented by pNode
                // i.e. string is on the edge to this node
                $substringLength = strlen( $pSubstring );
                for ($i=$pNode->start; $i < $pNode->start + $substringLength; $i++) { 
                    if ( substr( $text, $i, $substringLength ) === $pSubstring ) {
                        return 1;
                    }
                }
            }
        }

        // shorten
        $pSubstring = substr($pSubstring, $length );

        // else check childnodes for substring of pSubstring
        // substring is pSubstring without the letters represented by pNode
        $ret = -1;
        foreach ( $pNode->next as $childKey => $childValue ) {
            if ( $ret == 1 ) {
                // we found the substring in another path already
                return 1;
            }
            // check begin of substring with begin 
            // of string represented by this node
            if ( $childKey !== $pSubstring[0] ) {
                // substring does not start with 
                // the same letter -> check other paths
                continue;
            }
            // found a child
            // starting with the same letter -> check child
            $childNode          = $this->nodes[$childValue];
            $childNodeLength    = $childNode->end - $childNode->start;

            // pop letter of pSubstring and check with children
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
    public function hasSubstring( $pSubstring ) {
        if ( strlen( $pSubstring ) < 1 ) {
            return -1;
        }

        return $this->findSubstring( $this->nodes[$this->root], $pSubstring );
    }

    /**
     * Returns the amount of occurences of the given string
     * in the suffix tree. 
     * Use hasSubstring($pSubstring) to check, if the
     * given string is contained in the suffix tree.
     * 
     * @param  string  $pSubstring String to get the amount of occurences
     * @return integer             Returns -1, if length of $pSubstring is greater 
     *                                     than the string represented by the suffix tree.
     *                                     Else returns the amount of occurences of the
     *                                     given string.
     */
    public function getOccurence( $pSubstring ) {
        $substrLength   = strlen( $pSubstring );
        $textLength     = count( $this->text );
        $occurences     = 0;

        if ( $substrLength > $textLength ) {
            return -1;
        }
        $text = implode( '', $this->text );
        for ( $i=0; $i < $textLength - $substrLength + 1; $i++ ) { 
            if ( substr( $text, $i, $substrLength ) === $pSubstring ) {
                ++$occurences;
            }
        }

        return $occurences;
    }


    /**
     * Prints a simple representation of this suffix tree
     * 
     * @return string String to print.
     */
    public function __toString() {
        $s = "\nStart \tEnd \tLink \tidx \tsurprise value \tchildren\n";
        foreach ( $this->nodes as $node ) {
            $substrings = "";
            $keys = array_keys($node->next);
            
            foreach ( $keys as $key ) {
                $substrings .= "\t".$key." => ".$node->next[$key]."\n";
                $substrings .= "\t \t \t \t \t";
            }

            $end = $node->end;
            if ( $node->end > 1000000 )  {
                $end = "inf";
            }

            $s .= sprintf("%s \t%s \t%s \t%s \t%s \t%s \n", $node->start, 
                                                            $end, 
                                                            $node->link, 
                                                            $node->nodeIndex, 
                                                            $node->surpriseValue, 
                                                            $substrings);
        }

        return $s;
    }

}
?>
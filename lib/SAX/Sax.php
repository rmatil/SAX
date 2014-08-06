<?php
namespace Sax;

include("SuffixTree/SuffixTree.php");

use Sax\SuffixTree\SuffixTree;
use Sax\SuffixTree\Node;

/**
 * Class handling the functionality of the symbolic aggregate approximation
 * algorithm.
 *
 * @author Raphael Matile
 */
class Sax {

    /**
     * Tree representing all suffixes of 
     * the reference "sax word"
     * @var SuffixTree
     */
    public $referenceSuffixTree;

    /**
     * Trees representing all suffixes 
     * of the analysis sax words
     * @var SuffixTree
     */
    public $analysisSuffixTree;

    /**
     * Time series used as reference. Must 
     * contain keys 'count' and 'time'
     * @var array
     */
    private $referenceTimeSeries;

    /**
     * Array of time series under analysis.
     * Contains on each entry arrays representing time series.
     * These must contain keys 'count' and 'time'
     * @var array
     */
    private $analysisTimeSeries;

    /**
     * Normalized and discretized reference time series represented 
     * by a string over the alphabet $alphabet.
     * @var string
     */
    private $saxReferenceString;

    /**
     * Array of ormalized and discretized 
     * analysis time series represented 
     * by a string over the alphabet $alphabet.
     * @var array
     */
    private $saxAnalysisStrings;

    /**
     * Statistics of the reference time series, such as
     * min, max, mean, standard deviation, sum, number of entries
     * @var array
     */
    private $referenceStatistics;

    /**
     * Array of statistical values of the analysis time series, such as
     * min, max, mean, standard deviation, sum, number of entries
     * @var array
     */
    private $analysisStatistics;

    /**
     * The alphabet used to generate the 
     * "sax words"
     * @var array
     */
    private $alphabet;

    /**
     * Size of characters used to generate
     * the "sax words"
     * @var integer
     */
    private $alphabetSize;

    /**
     * Breakpoints needed to discretize
     * a time series to a sax word
     * @var array
     */
    private $breakpoints;

    /**
     * [__construct description]
     * @param array   $pReferenceTimeSeries Time series used as reference. Must contain 
     *                                      keys 'count' and 'time' on each entry.
     *                                      Count represents the number of occurences
     *                                      of an attribute at 'time'
     * @param array   $pAnalysisTimeSeries  Array of time series to analyse. Each time series
     *                                      must contain the keys 'count' and 'time' on each entry.
     *                                      Count represents the number of occurences of an 
     *                                      attribute at 'time'
     * @param integer $pAlphabetSize        Size of the alphabet used for discretization process.
     *                                      Must be greater than 2 and smaller than 11
     */
    public function __construct( array $pReferenceTimeSeries, array $pAnalysisTimeSeries, $pAlphabetSize = 5) {
        if ( $pAlphabetSize < 3 || $pAlphabetSize > 10 ) {
            throw new \Exception( "Alphabet size must be greater than 2 and smaller than 11." );
        }
        if ( count( $pReferenceTimeSeries ) < 1) {
            throw new \Exception( "Reference time series must contain some elements." );
        }

        if ( count( $pAnalysisTimeSeries ) < 1 ) {
            throw new \Exception("Analysis time series must contain some elements.");
        }

        foreach ($pReferenceTimeSeries as $entry) {
            if ( !isset($entry['count']) ||
                 !isset($entry['time'])) {
                throw new \Exception( "Reference time series must contain keys 'count' and 'time' in each element." );
            }    
        }

        foreach ($pAnalysisTimeSeries as $timeSeries) {
            foreach ($timeSeries as $entry) {
                if ( !isset($entry['count']) ||
                     !isset($entry['time'])) {
                    throw new \Exception( "Analysis time series must contain keys 'count' and 'time' in each element." );
                }   
            }   
        }

        $this->referenceTimeSeries  = $pReferenceTimeSeries;
        $this->analysisTimeSeries   = $pAnalysisTimeSeries;
        $this->alphabetSize         = intval( $pAlphabetSize );
        $this->analysisSuffixTree   = array();

        $this->initAlphabet();
        $this->initBreakpoints();
    }

    /**
     * Creates the suffix trees for the given reference string
     * and the strings under analysis. Annotates the occurences
     * of each substring in the corresponding node of the tree.
     * 
     * @param  string $pSaxReferenceString Discretized reference string (i.e. sax word)
     * @param  array  $pSaxAnalysisStrings Array of discretized string representing
     *                                     the time series under analysis
     * @return array                       Returns an array containing the analysis 
     *                                     trees annotated with their surprise values
     */
    public function preprocess( $pSaxReferenceString, array $pSaxAnalysisStrings) {
        $this->referenceSuffixTree = new SuffixTree( $pSaxReferenceString );

        foreach ( $pSaxAnalysisStrings as $anaString ) {
            $anaTree = new SuffixTree($anaString);
            $this->annotateSurpriseValues( $this->referenceSuffixTree, $anaTree );
            $this->analysisSuffixTree[] = $anaTree;
        }
        
        return $this->analysisSuffixTree;
    }


    /**
     * Calculates surprise values of the analysis time series 
     * in respect to the reference time series. If a surprise value
     * exceeds the given threshold, the pair ( index, surprise value ) is added
     * to the result array.
     * 
     * @param  integer $pFeatureWindowLength  Feature window length 
     *                                        used in discretization process for 
     *                                        dimensionality reduction
     * @param  integer $pScanningWindowLength Length of substrings to scan the 
     *                                        analysis series for surprise values
     * @param  array $pThreshold              Defines a boundary (upper & lower) for 
     *                                        surprise values for each analysis time series
     *                                        separate. Exceeding surprise values
     *                                        will be added to the result array
     * @return array                          Array containing the found surprise values. 
     *                                        Key is the analysis series sax word, values
     *                                        representing surprise pairs of ( index, surprise value )
     */
    public function tarzan( $pFeatureWindowLength, $pScanningWindowLength ) {
        $refStatistics              = $this->computeStatistics( $this->referenceTimeSeries );
        $normalizedRefSeries        = $this->normalizeTimeSeries( $this->referenceTimeSeries, 
                                                                  $refStatistics['mean'], 
                                                                  $refStatistics['stdDev'], 
                                                                  true );

        $normalizedAnaSeries = $this->normalizeTimeSeries( $this->analysisTimeSeries, 
                                                             $refStatistics['mean'], 
                                                             $refStatistics['stdDev'], 
                                                             false );

        // create sax words
        $refSaxWord         = $this->discretizeTimeSeries( $normalizedRefSeries, $pFeatureWindowLength );
        $anaSaxWords        = array();
        foreach ( $normalizedAnaSeries as $anaSeries ) {
            $anaSaxWords[]  = $this->discretizeTimeSeries( $anaSeries, $pFeatureWindowLength );
        }

        // annotate surprises
        $annotatedAnaTrees  = $this->preprocess( $refSaxWord, $anaSaxWords );

        // compute standard deviation of surprise values and 
        // using them as threshold
        $allThreshold = array();
        foreach ( $annotatedAnaTrees as $anaTree ) {
            $allSurprises = $anaTree->getAllSurpriseValues();

            for ($i=0; $i < count( $allSurprises ); $i++) { 
                $val = $allSurprises[$i];
                unset( $allSurprises[$i] );

                $allSurprises[$i]['count'] = $val;
            }
            $stats = $this->computeStatistics( $allSurprises );         
            $allThreshold[] = $stats['mean'] + ( $stats['stdDev'] / 2 );
        }

        // store surprises of each analysis sax word
        $surprises = array();
        for ( $i=0; $i < count( $annotatedAnaTrees ); $i++ ) {
            $anaTree = $annotatedAnaTrees[$i];
            // retreive surprise values for each substring
            $anaSaxWord = implode( '', $anaTree->text );
            for ($j=0; $j < strlen( $anaSaxWord ) - $pScanningWindowLength + 1; $j++) { 
                $w          = substr( $anaSaxWord, $j, $pScanningWindowLength );
                $surprise   = $anaTree->getSurpriseValue( $w );

                if ( abs( $surprise ) >= abs( $allThreshold[$i] ) && abs( $surprise ) > 0 ) {
                    $surprises[$anaSaxWord][] =  array( $j, $surprise );
                }
            }    
        }

        return $surprises;
    }

    /**
     * Calculates the minimum, maximum, standard deviation, mean, sum and the size
     * of the given time series. It must contain the key 'count' for each entry.
     * 
     * @param  array  $pTimeSeries Time series to calculate the described attributes
     * @return array               An array containing the attributes described above
     */
    public function computeStatistics( array $pTimeSeries ) {
        $statistics = array('min'       => 0,
                            'max'       => 0,
                            'stdDev'    => 0,
                            'mean'      => 0,
                            'sum'       => 0,
                            'size'      => count( $pTimeSeries ) );

        foreach ( $pTimeSeries as $entry ) {
            if ( $entry['count'] < $statistics['min'] ) {
                $statistics['min'] = $entry['count'];
            }
            if ( $entry['count'] > $statistics['max'] ) {
                $statistics['max'] = $entry['count'];
            }

            $statistics['sum'] += $entry['count'];
        }

        $statistics['mean'] = $statistics['sum'] / $statistics['size'];

        // standard deviation
        foreach ($pTimeSeries as $entry) {
            $statistics['stdDev'] += pow( $entry['count'] - $statistics['mean'], 2 );
        }
        if ( $statistics['size'] > 1 ) {
            $statistics['stdDev'] = sqrt( $statistics['stdDev'] / ( $statistics['size'] - 1 ) );
        } else {
            // standard deviation of a single element is 0
            $statistics['stdDev'] = 0;
        }
        
        return $statistics;   
    }

    /**
     * Normalizes a given time series to N(0,1) with
     * a given mean and standard deviation. 
     * isReference indicates whether the time series is only a one
     * dimensional timeseries representing the reference timeseries or
     * a two dimensional array representing an array of analysis time series.
     *
     * @param  array  $pTimeSeries Timeseries to normalize
     * @param  float $pMean       Mean to use for normalization
     * @param  float $pStdDev     Standard deviation to use for normalization
     * @param  bool $isReference  True, if $pTimeSeries represents the reference timeseries
     * @return array              The normalized timeseries
     */
    public function normalizeTimeSeries( array $pTimeSeries, $pMean, $pStdDev, $pIsReference ) {
        // prevent division by zero
        if ( $pStdDev === floatval(0) ) {
            $pStdDev = 1;
        }

        if ( $pIsReference ) {
            $this->referenceStatistics = $this->computeStatistics( $pTimeSeries );

            // normalize
            foreach ( $pTimeSeries as &$entry ) {
                $entry['count'] = ( $entry['count'] - $pMean ) / $pStdDev;
            }
            unset($entry);

        } else {
            foreach ( $pTimeSeries as &$timeSeries ) {
                $this->analysisStatistics[] = $this->computeStatistics( $timeSeries );

                // normalize
                foreach ( $timeSeries as &$entry ) {
                    $entry['count'] = ( $entry['count'] - $pMean ) / $pStdDev;
                }
                unset($entry);
            }
            unset($timeSeries);
        }

        return $pTimeSeries;
    }

    /**
     * Discretizes a given time series to a "sax word", i.e. a sequence
     * of characters indicating the amplitude of the time series.
     * @param  array   $pTimeSeries         Time series to discretize
     * @param  integer $pFeatureWindowLength Amount of datapoints which will used as a single 
     *                                      datapoint (by computing the mean), default is one
     * @return string                       The sax word
     */
    public function discretizeTimeSeries( array $pTimeSeries, $pFeatureWindowLength = 1 ) {
        $nrOfBreakpoints = $this->alphabetSize - 1;
        $breakpoints     = $this->breakpoints[$nrOfBreakpoints];

        $saxWord         = "";

        // discretize reference time series
        for ( $i=0; $i < count( $pTimeSeries ); $i+=$pFeatureWindowLength ) { 
            $datapoint = $pTimeSeries[$i]['count'];

            // dimensionality reduction using mean
            for ( $j=$i+1; $j < $pFeatureWindowLength; $j++ ) { 
                $datapoint += $pTimeSeries[$j]['count'];
            }
            $datapoint /= $pFeatureWindowLength;

            // discretize to sax word using breakpoints        
            for ( $z=0; $z < $nrOfBreakpoints + 1; $z++ ) {
                if ( isset( $breakpoints[$z] ) && $datapoint < $breakpoints[$z] ) {
                    // found first matching interval 
                    $saxWord .= $this->alphabet[$z];
                    break;
                } elseif ( $z === $nrOfBreakpoints ) {
                    // last datapoint, is greater than the last breakpoint
                    $saxWord .= $this->alphabet[$z];
                }
            }
        }

        return $saxWord;
    }

    /**
     * Annotates surprise values at each node of the analysis tree
     * in context of the reference tree
     * 
     * @param SuffixTree $pReferenceTree Suffix tree of the reference time series
     * @param SuffixTree $pAnalysisTree  Suffix tree of the time series under analysis
     */
    public function annotateSurpriseValues( &$pReferenceTree , &$pAnalysisTree) {
        $this->annotateNode( $pReferenceTree, $pAnalysisTree, $pAnalysisTree->nodes[$pAnalysisTree->root], "" );
    }

    /**
     * Annotates the analysis tree with surprise values in respect to the reference tree
     * in a recursive manner. 
     * 
     * @param  SuffixTree $pReferenceTree    Tree to use substring occurences as reference
     * @param  SuffixTree $pAnalysisTree     Tree on which to calculate surprise values
     * @param  Node       $pNode             Current active node ( on the beginnig: the root node )
     * @param  string     $representedString Substring of the whole string represented by the 
     *                                       analysis tree. Starting at the root, ending 
     *                                       on the active node.
     */
    private function annotateNode( SuffixTree &$pReferenceTree, SuffixTree &$pAnalysisTree, Node &$pNode, $representedString ) {
        if ( $pNode->start != -1 && $pNode->end != -1 ) {
            // is not the root node
            $word               = implode( '', $pAnalysisTree->text );
            $representedString .= substr( $word, $pNode->start, $pNode->end - $pNode->start );

            $scaleFactor        = ( count( $pAnalysisTree->text ) - strlen( $representedString ) + 1 ) /
                                  ( count( $pReferenceTree->text ) - strlen( $representedString) + 1 );
            $occurenceInRef     = 0;
            $surprise           = 0;

            if ( $pReferenceTree->hasSubstring( $representedString ) != -1 ) {
                // trivial case
                $occurenceInRef = $scaleFactor * $pReferenceTree->getOccurence( $representedString );
            } else {
                // check reference string for substrings
                $largestInterval = 1;
                // find largest length of substrings of represented string in the reference tree
                // such that each substring is contained in the reference tree
                // l = interval size
                // j = sliding index in representedString
                $largestFound = false;
                for ( $l=2; $l < strlen( $representedString ); $l++ ) { 
                    // starting at 2 because l must be greater than 1 
                    // according to the formula
                    
                    // if all substrings of the same
                    // length in this interval ($l) got found
                    $allSubstringsFound = true;
                    for ( $j=0; $j < strlen( $representedString ) - $l + 1; $j++ ) { 
                        $ret = $pReferenceTree->hasSubstring( substr( $representedString, $j, $l ) );
                        if ( $ret === -1 ) {
                            // substring of length '$l' is not contained anymore in 
                            // the reference string -> last interval size was the
                            // largest
                            $largestFound = true;
                            $allSubstringsFound = false;
                            break;
                        }
                    }

                    // all strings in this interval were
                    // found in the tree
                    if ( $allSubstringsFound == true ) {
                        $largestInterval = $l;
                    }

                    // don't increase interval size once a string
                    // is not found anymore
                    if ( $largestFound === true ) {
                        break;
                    }
                }
                if ( $largestInterval > 1 ) {
                    $counter        = 1;
                    $denominator    = 1;

                    for ( $j=0; $j < strlen( $representedString ) - $largestInterval + 1; $j++ ) { 
                        $counter       *= $pReferenceTree->getOccurence( substr( $representedString, $j, $largestInterval ) );
                    }
                    for ( $j=1; $j < strlen( $representedString ) - $largestInterval + 1; $j++ ) { 
                        $denominator   *= $pReferenceTree->getOccurence( substr( $representedString, $j, $largestInterval - 1 ) );
                    }
                    $occurenceInRef     = $scaleFactor * ( $counter / $denominator );
                } else {
                    // approximate the reference occurence by calculating the probability of appearance
                    // of each character of the representedString in the string represented by 
                    // the reference suffix tree
                    $probSum = 0;
                    for ($i=0; $i < strlen( $representedString ); $i++) { 
                        // number of occurences of each char in represented substring
                        $counter = 1;
                        for ($j=0; $j < strlen( $representedString ); $j++) { 
                            if ( $pReferenceTree->text[$j] == $representedString[$i] ) {
                                $counter++;
                            }
                        }
                        $denominator    = $pAnalysisTree->getOccurence( $representedString[$i] );
                        $probSum       += ( $counter / $denominator );
                    }
                    $occurenceInRef     = ( count( $pAnalysisTree->text ) + strlen( $representedString ) + 1 ) * $probSum;
                }
            }
            $pNode->surpriseValue = $pAnalysisTree->getOccurence( $representedString ) - $occurenceInRef;
        }

        // annotate children
        foreach ( $pNode->next as $childKey => $childValue ) {
            $this->annotateNode( $pReferenceTree, $pAnalysisTree, $pAnalysisTree->nodes[$childValue], $representedString );
        }
    }

    /**
     * Initialise breakpoints
     */
    private function initBreakpoints() {
        $this->breakpoints[0]   = -1;
        $this->breakpoints[1]   = -1;
        $this->breakpoints[2]   = array( -0.43, 0.43 );
        $this->breakpoints[3]   = array( -0.67, 0, 0.67 );
        $this->breakpoints[4]   = array( -0.84, -0.25, 0.25, 0.84 );
        $this->breakpoints[5]   = array( -0.97, -0.43, 0, 0.43, 0.97 );
        $this->breakpoints[6]   = array( -1.07, -0.57, -0.18, 0.18, 0.57, 1.07 );
        $this->breakpoints[7]   = array( -1.15, -0.67, -0.32, 0, 0.32, 0.67, 1.15 );
        $this->breakpoints[8]   = array( -1.22, -0.76, -0.43, -0.14, 0.14, 0.43, 0.76, 1.22 );
        $this->breakpoints[9]   = array( -1.28, -0.84, -0.52, -0.25, 0, 0.25, 0.52, 0.84, 1.28 );
    }

    /**
     * Initialise alphabet
     */
    private function initAlphabet() {
        $this->alphabet = array( "a", "b", "c", "d", "e", 
                                 "f", "g", "h", "i", "j" );
    }
}
?>
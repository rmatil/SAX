<?php
include("SuffixTree/SuffixTree.php");

class Sax {

    public $referenceSuffixTree;

    public $analysisSuffixTree;

    private $referenceTimeSeries;

    private $analysisTimeSeries;

    // TODO: create method to make 
    // a whole process of sax
    private $saxReferenceString;

    private $saxAnalysisStrings;

    private $referenceStatistics;

    private $analysisStatistics;

    private $alphabet;

    private $alphabetSize;

    private $breakpoints;

    public function __construct( array $pReferenceTimeSeries, array $pAnalysisTimeSeries, $pAlphabetSize = 5) {
        if ( count( $pReferenceTimeSeries ) < 1) {
            throw new Exception("Reference time series must contain some elements.");
        }

        if ( count( $pAnalysisTimeSeries ) < 1 ) {
            throw new Exception("Analysis time series must contain some elements.");
        }

        foreach ($pReferenceTimeSeries as $entry) {
            if ( !isset($entry['count']) ||
                 !isset($entry['time'])) {
                throw new Exception("Reference time series must contain keys 'count' and 'time' in each element.");
            }    
        }

        foreach ($pAnalysisTimeSeries as $timeSeries) {
            foreach ($timeSeries as $entry) {
                if ( !isset($entry['count']) ||
                     !isset($entry['time'])) {
                    throw new Exception("Analysis time series must contain keys 'count' and 'time' in each element.");
                }   
            }   
        }

        $this->referenceTimeSeries  = $pReferenceTimeSeries;
        $this->analysisTimeSeries   = $pAnalysisTimeSeries;
        $this->alphabetSize         = $pAlphabetSize;
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
        $this->referenceSuffixTree = new SuffixTree($pSaxReferenceString);

        foreach ($pSaxAnalysisStrings as $anaString) {
            $anaTree = new SuffixTree($anaString);
            $this->annotateSurpriseValues( $this->referenceSuffixTree, $anaTree );
            $this->analysisSuffixTree[] = $anaTree;
        }
        
        return $this->analysisSuffixTree;
    }


    public function tarzan( $pReferenceSaxWord, $pAnalysisSaxWord) {
        

    }

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
            $statistics['stdDev'] = sqrt( $statistics['stdDev'] / ( $statistics['size'] ) );
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
    public function normalizeTimeSeries( array $pTimeSeries, $pMean, $pStdDev, $isReference) {
        if ( $isReference ) {
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
     * @param  integer $featureWindowLength Amount of datapoints which will used as a single 
     *                                      datapoint (by computing the mean), default is one
     * @return string                       The sax word
     */
    public function discretizeTimeSeries( array $pTimeSeries, $featureWindowLength = 1 ) {
        $nrOfBreakpoints = $this->alphabetSize - 1;
        $breakpoints     = $this->breakpoints[$nrOfBreakpoints];
        $saxWord         = "";

        // discretize reference time series
        for ( $i=0; $i < count( $pTimeSeries ); $i+=$featureWindowLength ) { 
            $datapoint = $pTimeSeries[$i]['count'];

            // dimensionality reduction using mean
            for ( $j=$i+1; $j < $featureWindowLength; $j++ ) { 
                $datapoint += $pTimeSeries[$j]['count'];
            }
            $datapoint /= $featureWindowLength;

            // discretize to sax word using breakpoints        
            for ( $z=0; $z < $nrOfBreakpoints + 1; $z++ ) {
                if ( $datapoint < $breakpoints[$z] ) {
                    // found first matching interval 
                    $saxWord .= $this->alphabet[$z];
                    break;
                } elseif ( $z === $nrOfBreakpoints && $datapoint >= $breakpoints[$z] ) {
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

    private function annotateNode( SuffixTree &$pReferenceTree, SuffixTree &$pAnalysisTree, Node &$pNode, $representedString ) {
        if ( $pNode->start != -1 && $pNode->end != -1 ) {
            // is not the root node
            $word               = implode( '', $pAnalysisTree->text );
            $representedString .= substr( $word, $pNode->start, $pNode->end - $pNode->start );

            $scaleFactor        = ( count( $pReferenceTree->text ) - strlen( $representedString) + 1 ) /
                                  ( count( $pAnalysisTree->text ) - strlen( $representedString ) + 1 );
            $occurenceInRef     = 0;
            $surprise           = 0;


            if ( $pReferenceTree->hasSubstring( $representedString ) != -1 ) {
                // trivial case
                $occurenceInRef = $scaleFactor * $pReferenceTree->getOccurence( $representedString );
            } else {
                // check reference string for substrings
                $largestInterval = 0;
                // find largest length of substrings of represented string in the reference tree
                // such that each substring is contained in the reference tree
                // l = interval size
                // j = sliding index in representedString
                for ($l=1; $l < strlen( $representedString ); $l++) { 
                    // starting at 1 because length of 0 makes no sense...
                    
                    if ($largestInterval > 0) {
                        // found largest interval in step before
                        break;
                    }

                    for ($j=0; $j < strlen( $representedString ) - $l; $j++) { 
                        $ret = $pReferenceTree->hasSubstring( substr( $representedString, $j, $l ) );
                        
                        if ( $ret === -1 ) {
                            // substring of length '$l' is not contained anymore in 
                            // the reference string -> last interval size was the
                            // largest
                            $largestInterval = $l - 1;
                            break;
                        }
                    }
                }

                if ( $largestInterval > 0 ) {
                    $counter        = 0;
                    $denominator    = 1;

                    for ($j=0; $j < strlen( $representedString ) - $largestInterval; $j++) { 
                        $counter       *= $pReferenceTree->getOccurence( substr( $representedString, $j, $largestInterval ) );
                    }
                    for ($j=1; $j < strlen( $representedString ) - $largestInterval - 1; $j++) { 
                        $denominator   *= $pReferenceTree->getOccurence( substr( $representedString, $j, $largestInterval - 1) );
                    }

                    $occurenceInRef     = $scaleFactor * ( $counter / $denominator );
                } else {
                    $occurenceInRef     = $this->computeMarkovProbability( $pAnalysisTree, $representedString );
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
     * Calculates the amount of expected occurences of the given substring
     * in the reference tree by assuming a markov order of the length
     * of the given substring  - 2
     * 
     * @param  string $pSubstring Substring to calculate the expected amount of 
     *                            occurences
     * @return float             The expected count of occurences
     */
    private function computeMarkovProbability( SuffixTree $pAnalysisTree, $pSubstring ) {
        $analysisWord       = implode( '', $pAnalysisTree->text );
        $counter            = 0;
        $denominator        = 1;
        $markovChainOrder   = strlen( $pSubstring ) - 2;
        $expectedCount      = 0;

        for ($i=0; $i < strlen( $pSubstring ) - $markovChainOrder; $i++) { 
            $counter   *= $pAnalysisTree->getOccurence( substr( $pSubstring, $i, $markovChainOrder ) );
        }
        for ($i=1; $i < strlen( $pSubstring ) - $markovChainOrder - 1; $i++) { 
            $denominator   *= $pAnalysisTree->getOccurence( substr( $pSubstring, $i, $markovChainOrder - 1) );
        }

        return $counter / $denominator;
    }


    private function initBreakpoints() {
        $this->breakpoints[0]   = -1;
        $this->breakpoints[1]   = -1;
        $this->breakpoints[2]   = array(-0.43, 0.43);
        $this->breakpoints[3]   = array(-0.67, 0, 0.67);
        $this->breakpoints[4]   = array(-0.84, -0.25, 0.25, 0.84);
        $this->breakpoints[5]   = array(-0.97, -0.43, 0, 0.43, 0.97);
        $this->breakpoints[6]   = array(-1.07, -0.57, -0.18, 0.18, 0.57, 1.07);
        $this->breakpoints[7]   = array(-1.15, -0.67, -0.32, 0, 0.32, 0.67, 1.15);
        $this->breakpoints[8]   = array(-1.22, -0.76, -0.43, -0.14, 0.14, 0.43, 0.76, 1.22);
        $this->breakpoints[9]   = array(-1.28, -0.84, -0.52, -0.25, 0, 0.25, 0.52, 0.84, 1.28);
    }

    private function initAlphabet() {
        $this->alphabet = array("a", "b", "c", "d", "e", 
                                "f", "g", "h", "i", "j",
                                "k", "l", "m", "n", "o",
                                "p", "q", "r", "s", "t", 
                                "u", "v", "w", "x", "y", 
                                "z");
    }
}
?>*
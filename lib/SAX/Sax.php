<?php
include("SuffixTree/SuffixTree.php");

class Sax {

    private $referenceTimeSeries;

    private $analysisTimeSeries;

    private $referenceString;

    private $analysisStrings;

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

        $this->initAlphabet();
        $this->initBreakpoints();
    }

    public function computeStatistics( array $pTimeSeries ) {
        // TODO: assume count and time as keys in time series
        //       secured they exist in constructor
        $statistics = array('min'       => 0,
                            'max'       => 0,
                            'stdDev'    => 0,
                            'mean'      => 0,
                            'sum'       => 0,
                            'size'      => count( $pTimeSeries ) );

        foreach ( $pTimeSeries as $entry ) {
            if ( $entry < $statistics['min'] ) {
                $statistics['min'] = $entry;
            }
            if ( $entry > $statistics['max'] ) {
                $statistics['max'] = $entry;
            }

            $statistics['sum'] += $entry;
        }

        $statistics['mean'] = $statistics['sum'] / $statistics['size'];

        // standard deviation
        foreach ($pTimeSeries as $entry) {
            $statistics['stdDev'] += pow( $entry - $statistics['mean'], 2 );
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

            $refSeries = array();
            foreach ($pTimeSeries as $entry) {
                $refSeries[] = $entry['count'];
            }
            $this->referenceStatistics = $this->computeStatistics( $refSeries );

            // normalize
            foreach ( $pTimeSeries as &$entry ) {
                $entry['count'] = ( $entry['count'] - $pMean ) / $pStdDev;
            }
            unset($entry);

        } else {
            foreach ( $pTimeSeries as &$timeSeries ) {
                $analysisSeries = array();
                foreach ($timeSeries as $entry) {
                    $analysisSeries[] = $entry['count'];
                }
                $this->analysisStatistics[] = $this->computeStatistics( $analysisSeries );

                // normalize
                foreach ($timeSeries as &$entry) {
                    $entry['count'] = ( $entry['count'] - $pMean ) / $pStdDev;
                }
                unset($entry);
            }
            unset($timeSeries);
        }
        return $pTimeSeries;
    }

    public function discretizeTimeSeries( array $pTimeSeries ) {
        $nrOfBreakpoints = $this->alphabetSize - 1;
        $breakpoints     = $this->breakpoints[$nrOfBreakpoints];
        $saxWord         = "";

        // discretize reference time series
        foreach ( $pTimeSeries as $datapoint ) {
            for ( $i=0; $i < $nrOfBreakpoints + 1; $i++ ) {
                if ( $datapoint['count'] < $breakpoints[$i] ) {
                    // found first matching interval 
                    $saxWord .= $this->alphabet[$i];
                    break;
                } elseif ( $i === $nrOfBreakpoints && $datapoint['count'] >= $breakpoints[$i] ) {
                    // last datapoint, is greater than the last breakpoint
                    $saxWord .= $this->alphabet[$i];
                }
            }
        }

        return $saxWord;
    }


    private function initBreakpoints() {
        // TODO: try to compute dynamically
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
?>
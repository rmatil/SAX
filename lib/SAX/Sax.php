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

    public function __construct( array $pReferenceTimeSeries, array $pAnalysisTimeSeries) {
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
    }

    public function computeStatistics( array $pTimeSeries ) {
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
}
?>
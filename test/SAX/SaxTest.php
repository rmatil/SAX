<?php
include("../../lib/SAX/Sax.php");

class SaxTest extends PHPUnit_Framework_TestCase {

    protected static $referenceTimeSeries;

    protected static $analysisTimeSeries;

    protected static $errorTimeSeries;

    protected static $sax;

    public static function setUpBeforeClass() {
        self::$referenceTimeSeries  = array(
                                        array("time" => 123451, "count" => 2),
                                        array("time" => 123452, "count" => 1),
                                        array("time" => 123453, "count" => 6),
                                        array("time" => 123454, "count" => 4),
                                        array("time" => 123455, "count" => 5),
                                        array("time" => 123456, "count" => 0));

        self::$analysisTimeSeries   = array(
                                        array(
                                            array("time" => 123411, "count" => -1),
                                            array("time" => 123412, "count" => 1),
                                            array("time" => 123413, "count" => 2),
                                            array("time" => 123414, "count" => -1),
                                            array("time" => 123415, "count" => 6),
                                            array("time" => 123416, "count" => 30)),
                                        array(
                                            array("time" => 123421, "count" => 23),
                                            array("time" => 123422, "count" => 10),
                                            array("time" => 123423, "count" => 2),
                                            array("time" => 123424, "count" => 6),
                                            array("time" => 123425, "count" => 0),
                                            array("time" => 123426, "count" => 9)),
                                        array(
                                            array("time" => 123431, "count" => 50),
                                            array("time" => 123432, "count" => 100),
                                            array("time" => 123433, "count" => 2),
                                            array("time" => 123434, "count" => 1),
                                            array("time" => 123435, "count" => 7),
                                            array("time" => 123436, "count" => 2))
                                    );

        self::$errorTimeSeries      = array(array(1, 2, 3, 4, 5, 6));
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Reference time series must contain keys 'count' and 'time' in each element.
     */
    public function testConstructFail1() {
        $saxFail = new Sax( self::$errorTimeSeries, self::$analysisTimeSeries );
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Analysis time series must contain keys 'count' and 'time' in each element.
     */
    public function testConstructFail2() {
        $saxFail = new Sax( self::$referenceTimeSeries, self::$errorTimeSeries );
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Reference time series must contain some elements.
     */
    public function testConstructFail3() {
        $saxFail = new Sax( array(), self::$analysisTimeSeries );
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage Analysis time series must contain some elements.
     */
    public function testConstructFail4() {
        $saxFail = new Sax( self::$referenceTimeSeries, array() );
        $saxFail = new Sax( self::$referenceTimeSeries, array( array() ) );
    }

    public function testComputeStatistics() {
        $sax = new Sax( self::$referenceTimeSeries, self::$analysisTimeSeries );

        $statistics = $sax->computeStatistics(self::$referenceTimeSeries);

        $this->assertEquals( 3, $statistics['mean'] );
        $this->assertEquals( 0, $statistics['min'] );
        $this->assertEquals( 6, $statistics['max'] );
        $this->assertEquals( 6, $statistics['size'] );
        $this->assertEquals( 2.37, round( $statistics['stdDev'], 2) );
        $this->assertEquals( 18, $statistics['sum'] );
    }

    public function testNormalizeTimeSeriesTest() {
        $sax = new Sax( self::$referenceTimeSeries, self::$analysisTimeSeries );
        $statistics = $sax->computeStatistics(self::$referenceTimeSeries);

        $normalizedAnalysisSeries = $sax->normalizeTimeSeries( self::$analysisTimeSeries, 
                                                                $statistics['mean'], 
                                                                $statistics['stdDev'],
                                                                false);
        $this->assertEquals( -1.69, round( $normalizedAnalysisSeries[0][0]['count'], 2) );
        $this->assertEquals( -0.85, round( $normalizedAnalysisSeries[0][1]['count'], 2) );
        $this->assertEquals( -0.42, round( $normalizedAnalysisSeries[0][2]['count'], 2) );
        $this->assertEquals( -1.69, round( $normalizedAnalysisSeries[0][3]['count'], 2) );
        $this->assertEquals(  1.27, round( $normalizedAnalysisSeries[0][4]['count'], 2) );
        $this->assertEquals( 11.41, round( $normalizedAnalysisSeries[0][5]['count'], 2) );
    }

    public function testDiscretizeTimeSeries() {
        $sax = new Sax( self::$referenceTimeSeries, self::$analysisTimeSeries);

        // normalize reference series
        $series = array();
        foreach (self::$referenceTimeSeries as $entry) {
            $series[] = $entry;
        }
        // compute statistics of unnormalized series
        $statistics = $sax->computeStatistics(self::$referenceTimeSeries);
        $normalizedReferenceSeries = $sax->normalizeTimeSeries( self::$referenceTimeSeries,
                                                                $statistics['mean'],
                                                                $statistics['stdDev'], 
                                                                true);

        // normalize analysis time series using statistics of the reference string
        $normalizedAnalysisSeries = $sax->normalizeTimeSeries( self::$analysisTimeSeries, 
                                                                $statistics['mean'], 
                                                                $statistics['stdDev'],
                                                                false);
        $saxRefWord = $sax->discretizeTimeSeries($normalizedReferenceSeries);
        $saxAnaWord = array();
        foreach ($normalizedAnalysisSeries as $timeSeries) {
            $saxAnaWord[] = $sax->discretizeTimeSeries($timeSeries);
        }

        $this->assertEquals( 'baedea', $saxRefWord );
        $this->assertEquals( 'aabaee', $saxAnaWord[0] );
        $this->assertEquals( 'eebeae', $saxAnaWord[1] );
        $this->assertEquals( 'eebaeb', $saxAnaWord[2] );
    }

    public function testPreprocess() {
        $sax = new Sax( self::$referenceTimeSeries, self::$analysisTimeSeries);

        // compute statistics of unnormalized series
        $statistics = $sax->computeStatistics(self::$referenceTimeSeries);
        $normalizedReferenceSeries = $sax->normalizeTimeSeries( self::$referenceTimeSeries,
                                                                $statistics['mean'],
                                                                $statistics['stdDev'], 
                                                                true);

        // normalize analysis time series using statistics of the reference string
        $normalizedAnalysisSeries = $sax->normalizeTimeSeries( self::$analysisTimeSeries, 
                                                                $statistics['mean'], 
                                                                $statistics['stdDev'],
                                                                false);
        $saxRefWord = $sax->discretizeTimeSeries($normalizedReferenceSeries);
        $saxAnaWord = array();
        foreach ($normalizedAnalysisSeries as $timeSeries) {
            $saxAnaWord[] = $sax->discretizeTimeSeries($timeSeries);
        }

        // surprise values should be 0
        $sax->preprocess($saxRefWord, array($saxRefWord));

        // each node must have surprise = 0
        foreach ( $sax->analysisSuffixTree[0]->nodes as $node ) {
            $this->assertEquals(0, $node->surpriseValue);
        }
    }

    public function testTarzan() {
        $sax = new SAX( self::$referenceTimeSeries, self::$analysisTimeSeries );

        $surprises = $sax->tarzan( 1, 2 );

        var_dump($surprises);

        $this->assertEquals( 0, $surprises['aabaee'][0][0] );
        $this->assertEquals( 4, $surprises['aabaee'][1][0] );

        $this->assertEquals( 0, $surprises['eebeae'][0][0] );        

        $this->assertEquals( 0, $surprises['eebaeb'][0][0] );
    }
}
?>
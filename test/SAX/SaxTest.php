<?php
include("../../lib/SAX/Sax.php");

use Sax\Sax;

class SaxTest extends \PHPUnit_Framework_TestCase {

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

        $this->assertEquals( 0, $surprises['aabaee'][0][0] );
        $this->assertEquals( 1, $surprises['aabaee'][1][0] );

        $this->assertEquals( 0, $surprises['eebeae'][0][0] );        

        $this->assertEquals( 0, $surprises['eebaeb'][0][0] );
    }

    public function testZeroValues() {
        $ref  = array(
                                        array("time" => 123451, "count" => 0),
                                        array("time" => 123452, "count" => 0),
                                        array("time" => 123453, "count" => 0),
                                        array("time" => 123454, "count" => 0),
                                        array("time" => 123455, "count" => 0),
                                        array("time" => 123456, "count" => 0));

        $ana   = array(
                                        array(
                                            array("time" => 123411, "count" => 0),
                                            array("time" => 123412, "count" => 0),
                                            array("time" => 123413, "count" => 0),
                                            array("time" => 123414, "count" => 0),
                                            array("time" => 123415, "count" => 0),
                                            array("time" => 123416, "count" => 0))
                                    );
        $sax = new Sax( $ref, $ana);
        $surprises = $sax->tarzan(1, 4);

        $this->assertEquals(0, count( $surprises ) );
    }


    public function testOccurences() {
        $ref = array(
                                        array("time" => 1400544000, "count" => 0),
                                        array("time" => 1400547600, "count" => 0),
                                        array("time" => 1400551200, "count" => 0),
                                        array("time" => 1400554800, "count" => 0),
                                        array("time" => 1400558400, "count" => 0),
                                        array("time" => 1400562000, "count" => 0),
                                        array("time" => 1400565600, "count" => 0),
                                        array("time" => 1400569200, "count" => 2),
                                        array("time" => 1400572800, "count" => 0),
                                        array("time" => 1400576400, "count" => 0),
                                        array("time" => 1400580000, "count" => 0),
                                        array("time" => 1400583600, "count" => 0),
                                        array("time" => 1400587200, "count" => 13),
                                        array("time" => 1400590800, "count" => 22),
                                        array("time" => 1400594400, "count" => 1),
                                        array("time" => 1400598000, "count" => 8),
                                        array("time" => 1400601600, "count" => 1),
                                        array("time" => 1400605200, "count" => 5),
                                        array("time" => 1400608800, "count" => 4),
                                        array("time" => 1400612400, "count" => 4),
                                        array("time" => 1400616000, "count" => 6),
                                        array("time" => 1400619600, "count" => 3),
                                        array("time" => 1400623200, "count" => 0),
                                        array("time" => 1400626800, "count" => 0),
                                        array("time" => 1400630400, "count" => 0 )
                );
        $ana = array( 
                                    array( 
                                        array("time" => 1400630400, "count" => 0),
                                        array("time" => 1400634000, "count" => 0),
                                        array("time" => 1400637600, "count" => 0),
                                        array("time" => 1400641200, "count" => 0),
                                        array("time" => 1400644800, "count" => 0),
                                        array("time" => 1400648400, "count" => 0),
                                        array("time" => 1400652000, "count" => 5),
                                        array("time" => 1400655600, "count" => 2),
                                        array("time" => 1400659200, "count" => 5),
                                        array("time" => 1400662800, "count" => 5),
                                        array("time" => 1400666400, "count" => 1),
                                        array("time" => 1400670000, "count" => 3),
                                        array("time" => 1400673600, "count" => 2),
                                        array("time" => 1400677200, "count" => 2),
                                        array("time" => 1400680800, "count" => 2),
                                        array("time" => 1400684400, "count" => 88),
                                        array("time" => 1400688000, "count" => 25),
                                        array("time" => 1400691600, "count" => 15),
                                        array("time" => 1400695200, "count" => 12),
                                        array("time" => 1400698800, "count" => 3),
                                        array("time" => 1400702400, "count" => 10),
                                        array("time" => 1400706000, "count" => 6),
                                        array("time" => 1400709600, "count" => 1),
                                        array("time" => 1400713200, "count" => 2),
                                        array("time" => 1400716800, "count" => 0)
                                   )
                                );

        $sax = new Sax( $ref, $ana, 5);
        $surprises = $sax->tarzan(1, 3);

        $this->assertEquals(  4, $surprises["bbbbbbdcddbcccceeeecedbcb"][0][0] );
        $this->assertEquals(  5, $surprises["bbbbbbdcddbcccceeeecedbcb"][1][0] );
        $this->assertEquals(  6, $surprises["bbbbbbdcddbcccceeeecedbcb"][2][0] );
        $this->assertEquals(  7, $surprises["bbbbbbdcddbcccceeeecedbcb"][3][0] );
        $this->assertEquals(  8, $surprises["bbbbbbdcddbcccceeeecedbcb"][4][0] );
        $this->assertEquals( 10, $surprises["bbbbbbdcddbcccceeeecedbcb"][5][0] );
        $this->assertEquals( 13, $surprises["bbbbbbdcddbcccceeeecedbcb"][6][0] );
        $this->assertEquals( 14, $surprises["bbbbbbdcddbcccceeeecedbcb"][7][0] );
        $this->assertEquals( 17, $surprises["bbbbbbdcddbcccceeeecedbcb"][8][0] );
        $this->assertEquals( 18, $surprises["bbbbbbdcddbcccceeeecedbcb"][9][0] );
        $this->assertEquals( 19, $surprises["bbbbbbdcddbcccceeeecedbcb"][10][0] );
    }
}
?>
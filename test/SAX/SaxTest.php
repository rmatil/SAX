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

        $series = array();
        foreach (self::$referenceTimeSeries as $entry) {
            $series[] = $entry['count'];
        }
        $statistics = $sax->computeStatistics($series);

        $this->assertEquals( 3, $statistics['mean'] );
        $this->assertEquals( 0, $statistics['min'] );
        $this->assertEquals( 6, $statistics['max'] );
        $this->assertEquals( 6, $statistics['size'] );
        $this->assertEquals( 2.16, round( $statistics['stdDev'], 2) );
        $this->assertEquals( 18, $statistics['sum'] );
    }

    public function testNormalizeTimeSeriesTest() {
        $sax = new Sax( self::$referenceTimeSeries, self::$analysisTimeSeries );

        $series = array();
        foreach (self::$referenceTimeSeries as $entry) {
            $series[] = $entry['count'];
        }
        $statistics = $sax->computeStatistics($series);

        $normalizedAnalysisSeries = $sax->normalizeTimeSeries( self::$analysisTimeSeries, 
                                                                $statistics['mean'], 
                                                                $statistics['stdDev'],
                                                                false);
        $this->assertEquals( -1.8516, round( $normalizedAnalysisSeries[0][0]['count'], 4) );
        $this->assertEquals( -0.9258, round( $normalizedAnalysisSeries[0][1]['count'], 4) );
        $this->assertEquals( -0.4629, round( $normalizedAnalysisSeries[0][2]['count'], 4) );
        $this->assertEquals( -1.8516, round( $normalizedAnalysisSeries[0][3]['count'], 4));
        $this->assertEquals( 1.3887,  round( $normalizedAnalysisSeries[0][4]['count'], 4));
        $this->assertEquals( 12.4986, round( $normalizedAnalysisSeries[0][5]['count'], 4));
    }



}


?>
SAX
===

PHP Implementation of Symbolic Aggregate Approximation ([SAX](http://www.cs.ucr.edu/~eamonn/SAX.htm))

Implementation of the suffix tree is based on the idea of
"makagonov" on [Stackoverflow](http://stackoverflow.com/a/14580102)

### Installation
Using composer:  
```$ php composer.phar require "rmatil/sax":"dev-master"```  

### Usage
#### Step 1
Define a reference time series:   

```php
// time series used as reference
$referenceTimeSeries =  array(
                            array("time" => 123451, "count" => 2),
                            array("time" => 123452, "count" => 1),
                            array("time" => 123453, "count" => 6),
                            array("time" => 123454, "count" => 4),
                            array("time" => 123455, "count" => 5),
                            array("time" => 123456, "count" => 0)
                        );
```
#### Step 2
Define a single or multiple time series to analyse:

```php
// single / multiple time series to analyse
$analysisTimeSeries = array(
                        array(
                            array("time" => 123411, "count" => -1),
                            array("time" => 123412, "count" => 1),
                            array("time" => 123413, "count" => 2),
                            array("time" => 123414, "count" => -1),
                            array("time" => 123415, "count" => 6),
                            array("time" => 123416, "count" => 30)
                        ));

// create a new instance of sax using the time series from above using the default alphabet size of 5
$sax = new SAX( $referenceTimeSeries, $analysisTimeSeries );

// or define the alphabet size by yourself ( must be greater than 2 and smaller than 11 ) 
$sax = new SAX( $referenceTimeSeries, $analysisTimeSeries, 8);
```
#### Step 3
Define a feature window length and a scanning window length:
```php
// tarzan needs the feature window length and the scanning window length 
// as parameters
$surprises = $sax->tarzan( 1, 2 );
```

### Documentation 

An auto generated documentation can be found [here](http://rmatil.github.io/SAX/docs/).


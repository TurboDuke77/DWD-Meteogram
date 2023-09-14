<!DOCTYPE html>
<html lang="de">
<head>
<title>Meteogram</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8"><meta name="robots" content="noindex,nofollow">
</head>
<body>
<script src="libraries/custom/jquery_min.js"></script>
<script src="libraries/custom/sun.js"></script>
<script src="libraries/custom/meteogram.js"></script>
<script src="highstock/highstock.js"></script>
<script src="libraries/custom/windbarb.js"></script>

<div id="dwd_container" style="height: 320px; margin: 0 auto">
    <div style="margin-top: 100px; text-align: center" id="loading">
        <i class="fa fa-spinner fa-spin"></i> Lade Wetterdaten...
    </div>
</div>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

#============================================================================
#
# Download der MOSMIX_L_LATEST_$id.kmz vom DWD Server
#
# ============================================================================
#
# Author: Torsten Bondieck 
#
# ============================================================================

# Variablen -Start ------------------------------------------------>

# Der MOSMIX_L-Datensatz wird 4x täglich (ca. 115 Parameter, bis +240h) ab 03, 09, 15 und 21 Uhr UTC Zeit vom DWD bereit gestellt.
# https://www.dwd.de/DE/leistungen/met_verfahren_mosmix/met_verfahren_mosmix.html 
#
# Entgeltfreie Versorgung mit DWD-Geodaten über dem Serverdienst https://opendata.dwd.de
# https://opendata.dwd.de/README.txt
#
# DWD Stationskatalog mit der benötigten ID: 
# https://www.dwd.de/DE/leistungen/met_verfahren_mosmix/mosmix_stationskatalog.cfg?view=nasPublication&nn=16102
# z.B.: ID Trier = 10609, ID Koeln/Bonn = 10513, ID Bitburg = K428, ID Magdeburg = 10361, Chemnitz = 10577, Alesund = 01210

   # Station ID 
   $id = '10577'; # Chemnitz

   # Zeitbereich (max. 10 Tage = 240 Std sind möglich)
   $range = '240';

   # Ordner für Download der DWD  *.kmz Dateien
   $path = "./kmz/";

   # Download vom DWD Server 4x am Tag in bestimmten Zeitfenstern => true | oder Download bei jedem Aufruf => false
   $datareduce = true;

   # Angabe der gewünschten Zeitzone nach https://www.php.net/manual/en/timezones.php  (Schreibweise beachten!)
   $TZ = "Europe/Berlin";
   

# Variablen -Ende ------------------------------------------------<



#-Download KMZ Datei--------------------------------------------------------------------------------------------------->

$new = 0;

# ermitteln ob *.kmz Dateien mit anderer $id vorhanden sind
$path_array = glob($path.'*'.$id.'.kmz'); # nur Dateien mit der aktuellen $id in Array aufnehmen
//var_dump ($path_array);
if (count($path_array) == 0) {array_map('unlink', glob($path.'*.kmz'));} # alle Dateien im Pfad löschen

# UTC Offsets in Abhängigkeit der Timezone und Sommer/Winterzeit
   $timezone = new DateTimeZone($TZ);
   $UTC = ($timezone->getOffset(new DateTime()) / 3600);
   $UTCstr = sprintf("%+03d", $UTC).':00'; # Zahl mit Vorzeichen und führender Null

# Ermittlung ob *.kmz Datei aktuell
function ZeitpruefungMosmix ($updatezeiten){
	$UTC = $GLOBALS["UTC"];
	$Stunde=(date("G")); $Minute=(date("i"));
if ($Stunde == 0) {$Stunde = 24;}
	$ist=mktime($Stunde-$UTC, $Minute,0,0,0,0);
//echo $ist;
	$count = count($updatezeiten);$i=0;
	while ($i < $count)
		{
			$vh=substr($updatezeiten[$i][0],0,2); $vm=substr($updatezeiten[$i][0],3,2);
			$bh=substr($updatezeiten[$i][1],0,2); $bm=substr($updatezeiten[$i][1],3,2);
			$mostime=$updatezeiten[$i][2];
			$von=mktime($vh,$vm,0,0,0,0); $bis=mktime($bh,$bm,0,0,0,0);
	      			if (($von<=$ist)&&($bis>=$ist)){
	         			return $mostime;
	         		}
		$i++;
		}
	return false;
}

$updatezeiten =array( # von (xx:xx) bis (xx:xx) UTC Zeit!
	array("03:55","09:54","03"),	# MOSMIX_L_03_$id.kmz
	array("09:55","15:54","09"),	# MOSMIX_L_09_$id.kmz
	array("15:55","21:54","15"),	# MOSMIX_L_15_$id.kmz
	array("21:55","23:59","21"),	# MOSMIX_L_21_$id.kmz
	array("00:01","03:54","21")		# MOSMIX_L_21_$id.kmz
);

$update=ZeitpruefungMosmix($updatezeiten); # Rückgabe der erwarteten MOSMIX Update Zeit 03, 05, 15; 21 oder false
//echo 'MOSMIX_L_'.$update.'_'.$id.'.kmz <br>';

$mindiff = 360; # 360min = 6 Stunden (4x am Tag)

# Zeitdifferenz seit der letzten Dateiablage MOSMIX_L_'.$update.'_'.$id.'.kmz in Minuten
if (is_numeric($update)) {
	if (file_exists($path.'MOSMIX_L_'.$update.'_'.$id.'.kmz')) {
		$dateialter = filemtime($path.'MOSMIX_L_'.$update.'_'.$id.'.kmz');
		$jetzt = strtotime ('now');
		$mindiff = round(abs($dateialter - $jetzt) / 60, 0); # Dateialter in Minuten zusätzlich wegen Tageswechsel
	}
}

//echo $mindiff; # Dateialter MOSMIX_L_'.$update.'_'.$id.'.kmz in Minuten


# kein Download, wenn nicht im Update Zeitfenster  # Download immer starten, wenn $datareduce=false
if (!$update AND $datareduce) {
	//echo 'keine Updatezeit der DWD MOSMIX Daten<br>'; 
	//Return;
}

else if ($mindiff < 360 AND is_numeric($update) AND $datareduce AND count($path_array) > 0) {
	//echo 'aktuelle MOSMIX_L_'.$update.'_'.$id.'.kmz  bereits geladen'; 
//	Return; # Script beenden
}


else { # Download starten

//echo "...lade neue Daten vom DWD Server";

$strDatumStunde = date('YmdH');
$strZieldatei = 'MOSMIX_L_'.$strDatumStunde.'_'.$id.'.kmz';
$filename = $path.$strZieldatei;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://opendata.dwd.de/weather/local_forecasts/mos/MOSMIX_L/single_stations/$id/kml/MOSMIX_L_LATEST_$id.kmz");
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$fp = fopen($filename, 'w');
$mosfile = curl_exec($ch);
$intReturnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
fwrite($fp, $mosfile);
fclose($fp);
curl_close($ch);

# prüfe ob die Seite erreichbar war
if ($intReturnCode != 200 && $intReturnCode != 302 && $intReturnCode != 304) {
	echo 'ERROR: Seite opendata.dwd.de nicht erreichbar oder Station-ID falsch.';
	Return; # Script abbrechen
}

# downloaded source data (*.kmz) open ZIP
   $za = new ZipArchive();
   $za->open($filename);
   $stat = $za->statIndex(0);
# KML Datei in Variable einlesen
   $data = file_get_contents('zip://'.$filename.'#'.$stat['name']);  

# Zeit der KML Erstellung im Dateinamen verwenden 
    $xml2 = simplexml_load_string($data);
    $xmlDocument = $xml2->children('kml', true)->Document;
    $pubhourXML = (string) $xmlDocument->ExtendedData->children('dwd', true)->ProductDefinition->IssueTime;
    $pubhourXML = substr($pubhourXML, 11, 2);  # MOSMIX Update Zeit
    $newmosfilename = $path.'MOSMIX_L_'.$pubhourXML.'_'.$id.'.kmz';

if ($update == $pubhourXML OR !$datareduce) {
	rename ($filename, $newmosfilename); # wenn die Uhrzeit in der Datei zum Zeitfenster passt, die Datei umbenennen
      $new = 1;
	//echo 'MOSMIX_L_'.$pubhourXML.'_'.$id.'.kmz gespeichert';
}
else {
	unlink($filename); #  -> Datei löschen 
	//echo '<br>MOSMIX_L_LATEST_'.$id.'.kmz wurde noch nicht auf dem DWD Server aktualisiert';
	//Return; # und Abbruch
} 
}
# Ende *.kmz Download


#-Meteogram Erstellung --------------------------------------------------------------------------------------------------->
//return;

$maxTsFile = 0; 
$nFileName = ""; 
foreach(glob($path . '*.*') as $fileName) { 
  $ts = filemtime($fileName); 
  if($ts > $maxTsFile) { 
    $maxTsFile = $ts; 
    $nFileName = $fileName; 
  } 
} 
//echo "neuste Datei: " . $nFileName; 

# downloaded source data (*.kmz) open ZIP
   $za = new ZipArchive();
   $za->open($nFileName);
   $stat = $za->statIndex(0);
$filenameinKML = $stat['name'];
//echo $filenameinKML;
$kmzzeit = substr($filenameinKML, 17, 2); # Veröffentlichung Uhrzeit in KML Datei
$kmzdatumJ = substr($filenameinKML, 9, 4); # Veröffentlichung Datum Jahr
$kmzdatumD = substr($filenameinKML, 15, 2); # Veröffentlichung Datum Tag
$kmzdatumM = substr($filenameinKML, 13, 2); # Veröffentlichung Datum Monat

if ($new == 1) {
   $dwdquelle = 'Quelle: Deutscher Wetterdienst herausgegeben am '.$kmzdatumD.'.'.$kmzdatumM.'.'.$kmzdatumJ.' '.$kmzzeit.' Uhr UTC (MOSMIX_L)*gespeichert';
}
else {
   $dwdquelle = 'Quelle: Deutscher Wetterdienst herausgegeben am '.$kmzdatumD.'.'.$kmzdatumM.'.'.$kmzdatumJ.' '.$kmzzeit.' Uhr UTC (MOSMIX_L)*aktuell';
}

# Ende PHP Code
?>

<script>
/**
 * ============================================================================
 *
 * Meteogram for DWD MOSMIX forecast stations. Dervied by Highcharts meteogram
 * demo.
 *
 * ============================================================================
 *
 * Author: Michael Buchfink
 *
 * ============================================================================
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
**/

  var hours_short = 24;
//  var hours_long = 240;
var hours_long = '<?=$range?>'; // PHP Variable an JS Variable übergeben

// var mosmix_url = 'data/10361.json';
// var mosmix_url = 'https://buwx.de/charts/meteogram/mosmix.php?station=10471&title=Leipzig&titleShort=Leipzig';
var mosmix_url = 'data/mosmix.php';



  /**
   * Statistically adjust forecast parameters, based on historical data from private Station Scharnhausen.
   *
   * Call:  glm(formula = diff1 ~ sun_pos + dwd_n + diff_td + dwd_ff, data = dftt)
   *
   * Coefficients:
   * (Intercept)      sun_pos        dwd_n      diff_td       dwd_ff  
   *     -1.3731       0.0137       0.0942       0.0183       0.0445  
   *
   * Degrees of Freedom: 26099 Total (i.e. Null);  26095 Residual
   * Null Deviance:      37800 
   * Residual Deviance: 25000   AIC: 72900
   *
   *
   * Call:  glm(formula = diff2 ~ diff_td + dwd_n + dwd_ff, data = dttd)
   *
   * Coefficients:
   * (Intercept)      diff_td        dwd_n       dwd_ff  
   *     -0.5052       0.1119       0.0831       0.0119  
   *
   * Degrees of Freedom: 26099 Total (i.e. Null);  26096 Residual
   * Null Deviance:      23100 
   * Residual Deviance: 16900   AIC: 62700
   */
  function adjust(meteogram) {
      //meteogram.dwd.station.title = 'Magdeburg';
      //meteogram.dwd.station.titleShort = 'Magdeburg';

      var lon = meteogram.dwd.station.coordinates[0];
      var lat = meteogram.dwd.station.coordinates[1];
      for (i=0; i < hours_long; i++) {
          var time = new Date(meteogram.temperature[i].x);
          var year = time.getUTCFullYear();
          var month = time.getUTCMonth();
          var day = time.getUTCDate();
          var hour = time.getUTCHours();
          var min = time.getUTCMinutes();
          var offset = hour + min/60 - 1.5;
          var sun_pos = calcZenith(calcJD(year, month + 1, day), offset, lat, -lon);
          var dwd_n = parseFloat(meteogram.wmo_n[i].n);
          var diff_td = parseFloat(meteogram.dwd.station.TTT[i]) - parseFloat(meteogram.dwd.station.Td[i]);
          var dwd_ff = parseFloat(meteogram.dwd.station.FF[i]) * 3.6;

          var diff1 = -1.3731 + 0.0137*sun_pos + 0.0942*dwd_n + 0.0183*diff_td + 0.04454*dwd_ff;
          var diff2 = -0.5052 + 0.1119*diff_td + 0.0831*dwd_n + 0.0119*dwd_ff;

          var tt = Math.round((parseFloat(meteogram.dwd.station.TTT[i])-273.15+diff1) * 10)/10;
          var td = Math.round((parseFloat(meteogram.dwd.station.Td[i])-273.15+diff2) * 10)/10;
          if (td > tt) {
              td = tt;
          }

          meteogram.temperature[i].y = tt;
          meteogram.dewpoint[i].y = td;
      }
  }

  /**
   * Meteogram derived by Highcharts meteogram demo for using DWD MESOMIX data.
   */
  function Meteogram(dwd, container) {
      this.temperature = [];
      this.dewpoint = [];
      this.pressure = [];
      this.precipitation = [];
      this.wind = [];
      this.sunshine = [];
      this.extrema = [];

      this.temperature_short = [];
      this.dewpoint_short = [];
      this.pressure_short = [];
      this.precipitation_short = [];
      this.wind_short = [];
      this.sunshine_short = [];
      this.extrema_short = [];

      this.wmo_n = [];
      this.wmo_ww = [];

      this.plotbands = [];

      // Initialize
      this.dwd = dwd;
      this.container = container;

      // Run
      this.parseDwdData();
  }

  /**
   * Draw the weather symbols.
   */
  Meteogram.prototype.drawWmoSymbols = function (chart) {
      var meteogram = this;

      jQuery.each(meteogram.wmo_n, function (i, wmo) {
          if (wmo.image) {
              wmo.image.destroy();
              wmo.image = null;
          }
      });
      jQuery.each(meteogram.wmo_ww, function (i, wmo) {
          if (wmo.image) {
              wmo.image.destroy();
              wmo.image = null;
          }
      });

      var n_size = Math.min(10, chart.plotWidth / chart.series[0].data.length);
      var ww_size = Math.min(16, chart.plotWidth / chart.series[0].data.length);
      jQuery.each(chart.series[0].data, function (i, point) {
          if (!meteogram.wmo_n[i].image) {
              var image = chart.renderer.image(
                  'wmo/n/' + meteogram.wmo_n[i].n + '.svg',
                   point.plotX + chart.plotLeft - n_size/2,
                   chart.plotTop + chart.plotHeight + n_size/2,
                   n_size, n_size
              )
              .attr({
                  zIndex: 3
               })
               .add();
               meteogram.wmo_n[i].image = image;
          }
          if (!meteogram.wmo_ww[i].image && meteogram.wmo_ww[i].ww) {
              var image = chart.renderer.image(
                  'wmo/ww/' + meteogram.wmo_ww[i].ww + '.svg',
                   point.plotX + chart.plotLeft - ww_size/2,
                   point.plotY + chart.plotTop - 15,
                   ww_size, ww_size
              )
              .attr({
                  zIndex: 3
               })
//               .add();
//               meteogram.wmo_ww[i].image = image;
          }
      });
  }

  Meteogram.prototype.getChartOptions = function () {
      var meteogram = this;

      return {
          chart: {
              renderTo: this.container,
              marginBottom: 70,
              marginRight: 40,
              marginTop: 50,
              height: 462,
              plotBorderWidth: 1,
              alignTicks: false,
              plotBackgroundColor: '#fffdf0',
              events: {
                  redraw: function () {
                      meteogram.drawWmoSymbols(this);
                  }
              }
          },

          time: {
              useUTC: false
          },

          title: {
              text: 'Vorhersage für ' + this.dwd.station.title,
              align: 'left'
          },

          credits: {
              //text: 'herausgegeben am Quelle: Deutscher Wetterdienst (' + this.dwd.productId + ')',
              text: '<?=$dwdquelle?>',  
              href: 'https://www.dwd.de/opendata',
              position: {
                  x: -40,
                  y: -2
              }
          },

          tooltip: {
              shared: true,
              useHTML: true,
              headerFormat: '<small>{point.x:%A, %e. %B %H} Uhr</small><br>'
          },

          xAxis: [{ // Bottom X axis
              type: 'datetime',
              tickInterval: 2 * 36e5, // two hours
              minorTickInterval: 36e5, // one hour
              tickLength: 0,
              gridLineWidth: 1,
              gridLineColor: '#F0F0F0',
              startOnTick: false,
              endOnTick: false,
              minPadding: 0,
              maxPadding: 0,
              offset: 40,
              showLastLabel: true,
              labels: {
                  x: 7,
                  format: '{value:%H}'
              },
              crosshair: true,
              plotBands: this.plotbands
          }, { // Top X axis
              linkedTo: 0,
              type: 'datetime',
              tickInterval: 24 * 3600 * 1000,
              labels: {
                  format: '{value:%a %e. %b}',
                  align: 'left',
                  x: 3,
                  y: -5,
                  style: {
                      whiteSpace: 'nowrap',
					  fontSize: '10px'
                  }
              },
              opposite: true,
              tickLength: 20,
              gridLineWidth: 1
          }],

          yAxis: [{ // temperature axis
              title: {
                  text: null
              },
              labels: {
                  format: '{value}°',
                  style: {
                      fontSize: '10px'
                  },
                  x: -3
              },
              plotLines: [{ // zero plane
                  value: 0,
                  color: '#BBBBBB',
                  width: 1,
                  zIndex: 2
              }],
              maxPadding: 0.3,
              minRange: 8,
              tickInterval: 1,
              gridLineColor: '#F0F0F0'
          }, { // precipitation axis
              title: {
                  text: null
              },
              labels: {
                  enabled: false
              },
              gridLineWidth: 0,
              tickLength: 0,
              minRange: 1.5,
              min: 0
          }, { // sunshine axis
              title: {
                  text: null
              },
              labels: {
                  enabled: false
              },
              gridLineWidth: 0,
              tickLength: 0,
              max: 240,
              min: 0
          }, { // Air pressure
              allowDecimals: false,
              title: { // Title on top of axis
                  text: 'hPa',
                  offset: 0,
                  align: 'high',
                  rotation: 0,
                  style: {
                      fontSize: '10px',
                      color: '#90ed7d'
                  },
                  textAlign: 'left',
                  x: 3
              },
              labels: {
                  style: {
                      fontSize: '8px',
                      color: '#90ed7d'
                  },
                  y: 2,
                  x: 3
              },
              gridLineWidth: 0,
              opposite: true,
              showLastLabel: false,
              showInLegend: false
          }],

          plotOptions: {
              series: {
                  pointPlacement: 'between'
              }
          },

          series: [{
              id: 'Temperatur',
              name: 'Temperatur',
              type: 'spline',
              data: this.temperature,
              marker: {
                  enabled: false,
                  states: {
                      hover: {
                          enabled: true
                      }
                  }
              },
              tooltip: {
                  pointFormat: '<span style="color:{point.color}">\u25CF</span> ' +
                      '{series.name}: <b>{point.y}°C</b><br/>'
              },
              zIndex: 2,
              color: '#FF3333',
              negativeColor: '#48AFE8',
              showInLegend: false
          }, {
              id: 'Taupunkt',
              name: 'Taupunkt',
              type: 'spline',
              data: this.dewpoint,
              marker: {
                  enabled: false,
              },
              tooltip: {
                  pointFormat: '<span style="color:{point.color}">\u25CF</span> ' +
                      '{series.name}: <b>{point.y}°C</b><br/>'
              },
              dashStyle: 'dash',
              zIndex: 2,
              color: '#819FF7',
              showInLegend: false
          }, {
              id: 'Niederschlag',
              name: 'Niederschlag',
              type: 'column',
              data: this.precipitation,
              color: '#68CFE8',
              yAxis: 1,
              groupPadding: 0,
              pointPadding: 0,
              grouping: false,
              dataLabels: {
                  enabled: true,
                  formatter: function () {
                      if (this.y > 0) {
                          return this.y;
                      }
                  },
                  style: {
                      fontSize: '8px',
                      color: 'gray'
                  }
              },
              tooltip: {
                  valueSuffix: ' mm'
              },
              showInLegend: false
          }, {
              id: 'Sonnenschein',
              name: 'Sonnenschein',
              type: 'column',
              data: this.sunshine,
              color: 'gold',
              yAxis: 2,
              groupPadding: 0,
              pointPadding: 0.2,
              grouping: false,
              dataLabels: {
                  enabled: false
              },
              tooltip: {
                  valueSuffix: ' min'
              },
              showInLegend: false
          }, {
              id: 'Luftdruck',
              name: 'Luftdruck',
              type: 'spline',
              data: this.pressure,
              color: '#90ed7d',
              marker: {
                  enabled: false
              },
              shadow: false,
              tooltip: {
                  valueSuffix: ' hPa'
              },
              dashStyle: 'shortdot',
              zIndex: 1,
              yAxis: 3,
              showInLegend: false
          }, {
              id: 'Wind',
              name: 'Wind',
              type: 'windbarb',
              data: this.wind,
              color: '#434348',
              lineWidth: 1.5,
              vectorLength: 12,
              yOffset: -10,
              tooltip: {
                  pointFormat: '<span style="color:{point.color}">\u25CF</span> {series.name}: <b>{point.value_kmh} km/h {point.direction_text}</b><br/>{point.n_text}<br/>{point.ww_text}'
              },
              showInLegend: false
          }, {
              id: 'Extrema',
              name: 'Extrema',
              type: 'flags',
              data: this.extrema,
              onSeries : 'Temperatur',
              shape: 'squarepin',
              zIndex: 5,
              style: {
                  fontSize: '9px',
                  fontWeight: 'normal'
              },
              tooltip: {
                  headerFormat: '<small>{point.x:%A, %e. %B %H} Uhr</small><br>'
              },
              showInLegend: false
          }],

          responsive: {
              rules: [{
                  condition: {
                      maxWidth: 500
                  },
                  chartOptions: {
                      chart: {
                          height: 400
                      },
                      title: {
                          text: 'Vorhersage für ' + this.dwd.station.titleShort,
                          align: 'left'
                      },
                      series: [{
                          id: 'Temperatur',
                          data: this.temperature_short
                      }, {
                          id: 'Taupunkt',
                          data: this.dewpoint_short
                      }, {
                          id: 'Niederschlag',
                          data: this.precipitation_short
                      }, {
                          id: 'Sonnenschein',
                          data: this.sunshine_short
                      }, {
                          id: 'Luftdruck',
                          data: this.pressure_short
                      }, {
                          id: 'Wind',
                          data: this.wind_short
                      }, {
                          id: 'Extrema',
                          data: this.extrema_short
                      }]
                  }
              }, {
                  condition: {
                      minWidth: 501
                  },
                  chartOptions: {
                      title: {
                          text: 'Vorhersage für ' + this.dwd.station.title,
                          align: 'left'
                      },
                      series: [{
                          id: 'Temperatur',
                          data: this.temperature
                      }, {
                          id: 'Taupunkt',
                          data: this.dewpoint
                      }, {
                          id: 'Niederschlag',
                          data: this.precipitation
                      }, {
                          id: 'Sonnenschein',
                          data: this.sunshine
                      }, {
                          id: 'Luftdruck',
                          data: this.pressure
                      }, {
                          id: 'Wind',
                          data: this.wind
                      }, {
                          id: 'Extrema',
                          data: this.extrema
                      }]
                  }
              }]
          }
      }
  }


  /**
   * Create the chart. This function is called async when the data file is loaded and parsed.
   */
  Meteogram.prototype.createChart = function () {
      Highcharts.setOptions({
          lang: {
              decimalPoint: ',',
              thousandsSep: '.',
              loading: 'Daten werden geladen...',
              months: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
              weekdays: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
              shortMonths: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
              shortWeekdays: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
              contextButtonTitle: "Exportieren",
              printChart: "Drucken",
              rangeSelectorFrom: "Von",
              rangeSelectorTo: "Bis",
              rangeSelectorZoom: "Zeitraum",
              downloadPNG: 'Download als PNG-Bild',
              downloadJPEG: 'Download als JPEG-Bild',
              downloadPDF: 'Download als PDF-Dokument',
              downloadSVG: 'Download als SVG-Bild',
              resetZoom: "Zoom zurücksetzen",
              resetZoomTitle: "Zoom zurücksetzen"
          }
      });
      var meteogram = this;
      this.chart = new Highcharts.Chart(this.getChartOptions());
  }

  Meteogram.prototype.error = function () {
      jQuery('#loading').html('<i class="fa fa-frown-o"></i> Daten konnten nicht geladen werden');
  }

  /**
   * Handle the data.
   */
  Meteogram.prototype.parseDwdData = function () {

      var meteogram = this,
          fromTotal, toTotal,
          pointStart, i;

      if (!this.dwd || !this.dwd.timeSteps || !this.dwd.station) {
          return this.error();
      }

      for (i=0; i < hours_long; i++) {
          var from = this.dwd.timeSteps[i];
          from = from.replace(/-/g, '/').replace('T', ' ').replace('.000Z', ' UTC');
          from = Date.parse(from);
          var to = from + 36e5;

          // If it is more than an hour between points, show all symbols
          if (i === 0) {
              fromTotal = from;
              pointStart = (from + to) / 2;
          }
          if (i === hours_long - 1) {
              toTotal = to;
          }

          meteogram.temperature.push({
              x: from,
              y: Math.round((parseFloat(this.dwd.station.TTT[i])-273.15) * 10)/10,
              to: to
          });

          meteogram.dewpoint.push({
              x: from,
              y: Math.round((parseFloat(this.dwd.station.Td[i])-273.15) * 10)/10
          });

          meteogram.pressure.push({
              x: from,
              y: parseFloat(this.dwd.station.PPPP[i])/100.0
          });

          meteogram.precipitation.push({
              x: from,
              y: parseFloat(this.dwd.station.RR1c[i+1])
          });

          var wind_speed = parseFloat(this.dwd.station.FF[i]);
          var wind_direction = parseFloat(this.dwd.station.DD[i]);
          meteogram.wind.push({
              x: from,
              value: wind_speed,
              direction: wind_direction,
              value_kmh: Math.round(wind_speed * 3.6),
              direction_text: directionText(wind_speed, wind_direction)
          });

          meteogram.sunshine.push({
              x: from,
              // y: Math.round(parseFloat(this.dwd.station.SunD1[i+1])/60) // mit +1 nicht mehr passend zu den DWD Daten
              y: Math.round(parseFloat(this.dwd.station.SunD1[i])/60)              
          });

          var n = 'Slash';
          var n_text = '';
          if (this.dwd.station.Neff[i] && this.dwd.station.Neff[i] !== '-') {
              var n_value = parseInt(this.dwd.station.Neff[i]);
              if (n_value == 0) {
                  n = '0';
              }
              else if (n_value <= 10) {
                  n = '1';
              }
              else if (n_value <= 30) {
                  n = '2';
              }
              else if (n_value <= 40) {
                  n = '3';
              }
              else if (n_value <= 50) {
                  n = '4';
              }
              else if (n_value <= 60) {
                  n = '5';
              }
              else if (n_value <= 80) {
                  n = '6';
              }
              else if (n_value < 99) {
                  n = '7';
              }
              else {
                  n = '8';
                  n_value = 100;
              }
              n_text = 'Bedeckungsgrad: ' + n_value + '%';
          }
          meteogram.wmo_n.push({
              x: from,
              n: n,
              text: n_text,
              image: null
          });
          meteogram.wind[i].n_text = n_text;

          var ww = null;
          var ww_text = '';
          if (this.dwd.station.ww[i+1] && this.dwd.station.ww[i+1] !== '-') {
              var ww_value = parseInt(this.dwd.station.ww[i+1]);
              if (ww_value >= 10) {
                  ww = ww_value.toString();
                  ww_text = ww_messages[ww];
              }
          }
          meteogram.wmo_ww.push({
              x: from,
              ww: ww,
              text: ww_text,
              image: null
          });
          meteogram.wind[i].ww_text = ww_text;
      }
      //adjust(meteogram);
      meteogram.temperature_short = meteogram.temperature.slice(0, hours_short);
      meteogram.dewpoint_short = meteogram.dewpoint.slice(0, hours_short);
      meteogram.pressure_short = meteogram.pressure.slice(0, hours_short);
      meteogram.precipitation_short = meteogram.precipitation.slice(0, hours_short);
      meteogram.wind_short = meteogram.wind.slice(0, hours_short);
      meteogram.sunshine_short = meteogram.sunshine.slice(0, hours_short);

      // Initialize plotbands
      var lon = this.dwd.station.coordinates[0];
      var lat = this.dwd.station.coordinates[1];
      var time = new Date(fromTotal);
      time.setUTCHours(0, 0, 0, 0);

      var sunSet = time.getTime();
      do {
          var year = time.getUTCFullYear();
          var month = time.getUTCMonth() + 1;
          var day = time.getUTCDate();

          var sunRiseOffset = calcSunriseUTC(calcJD(year, month, day), lat, -lon) + 30;
          var sunSetOffset = calcSunsetUTC(calcJD(year, month, day), lat, -lon) + 30;

          meteogram.plotbands.push({
              color: '#f0fffe',
              from: sunSet,
              to: time.getTime() + sunRiseOffset * 6e4
          });

          sunSet = time.getTime() + sunSetOffset * 6e4;
          time = new Date(time.getTime() + 24*36e5);

     } while (time.getTime() < to);

      meteogram.plotbands.push({
          color: '#f0fffe',
          from: sunSet,
          to: toTotal
      });

     // find extremas
     findExtremas(meteogram.temperature, meteogram.extrema);
     findExtremas(meteogram.temperature_short, meteogram.extrema_short);
     
     // Create the chart when the data is loaded
     this.createChart();
  }
  // End of the Meteogram protype

  // On DOM ready...
  jQuery.getJSON(
    mosmix_url,
    function (dwd) {
        window.meteogram = new Meteogram(dwd, 'dwd_container');
    }
);
</script>
</body>
</html>

<?php
/*
 * ============================================================================
 *
 * Converts forecast data for a given station to json.
 *
 * ============================================================================
 *
 * Author: Michael Buchfink
 *
 * ============================================================================
 *
 * Last updated: 15.03.2018
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
 */
//ini_set('display_errors', 1);
//error_reporting(E_ALL); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$path = '../kmz/'; 

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
//echo substr(basename($nFileName), 9, 2); # Veröffentlichung Uhrzeit
//echo date( "d.m.Y", $maxTsFile); # Download Datum

# downloaded source data (*.kmz) open ZIP
   $za = new ZipArchive();
   $za->open($nFileName);
   $stat = $za->statIndex(0);
# KML Datei in Variable einlesen
   $data = file_get_contents('zip://'.$nFileName.'#'.$stat['name']);

//echo $data;

$xml2 = simplexml_load_string($data);
$xmlDocument = $xml2->children('kml', true)->Document;
//$station = '10361';
$station = (string) $xmlDocument->Placemark->name; # Station ID aus KML auslesen
if (isset($_GET['station'])) {
    $station = $_GET['station'];
}
//$title = 'Magdeburg';
$title = (string) $xmlDocument->Placemark->description; # Stationsname aus KML
if (isset($_GET['title'])) {
    $title = $_GET['title'];
}

$titleShort = $title;
if (isset($_GET['titleShort'])) {
    $titleShort = $_GET['titleShort'];
}

$xsl = 'mos-json.xsl';
if (isset($_GET['opt'])) {
    $xsl = 'mos-json-opt.xsl';
}

$xslDoc = new DOMDocument();
$xslDoc->load($xsl);

$xmlDoc = new DOMDocument();
//$xmlDoc->load('MOSMIX_L_10361.kml');
$xmlDoc->loadXML($data);

$proc = new XSLTProcessor();
$proc->importStylesheet($xslDoc);

$proc->setParameter('', 'station', $station);
$proc->setParameter('', 'title', $title);
$proc->setParameter('', 'titleShort', $titleShort);

echo $proc->transformToXML($xmlDoc);

//$jsonmos = $proc->transformToXML($xmlDoc);
//file_put_contents('10361.json', $jsonmos);

?>

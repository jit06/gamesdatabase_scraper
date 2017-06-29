<?php
/* @Author   : julien Tiphaine (www.bluemind.org)
 * @Date     : 15.05.2017 
 *
 * This quick & dirty script tries to retreive a video preview of a game for a given system.
 * It is based on a dirty parsing of gamesdatabase.org website and is based on the way this web site has been done.
 *
 * Thus it may not work in the future, if the website's logic change.
 *
 */


/**************************************************************************************************
 *
 * Settings
 *
 *************************************************************************************************/
define("SEARCH_URL_BASE", "http://www.gamesdatabase.org/list.aspx?in=1&searchtype=1&searchtext=");
define("VIDEO_PATH_BASE", "http://gamesdatabase.org/Media/SYSTEM/%s/Video/Formated/%s");
define("VIDEO_EXTENSION", ".mp4");
define("IMG_EXTENSION"  , ".jpg");



/**************************************************************************************************
 *
 * Main program
 *
 *************************************************************************************************/

// no way o continue if not enought arguments
if($argc != 4) {
    die("Usage : scraper.php <game name> <system name> <output file>");
}

// make things clear ;)
$STRING_TO_SEARCH = $argv[1];
$SYSTEM_TO_SEARCH = $argv[2]; 
$FILENAME_TO_WRITE= $argv[3];

// start scraping from the site
$url = getSearchUrl($STRING_TO_SEARCH);
if(!$html = getHtmlString($url)) {
    die("empty html\n");
}

// we have HTML, search for the system in results
$elements = searchForEntry($SYSTEM_TO_SEARCH, $html);
if (is_null($elements) || $elements->length == 0) {

    // try harder by removing some characters...
    $url = getSearchUrl(str_replace(Array('é','-',':','!'),Array('e',''),$STRING_TO_SEARCH));
    if(!$html = getHtmlString($url)) {
        die("empty html\n");
    }
    $elements = searchForEntry($SYSTEM_TO_SEARCH, $html);
    if (is_null($elements) || $elements->length == 0) {
        die("no entry found for game '$STRING_TO_SEARCH' on system '$SYSTEM_TO_SEARCH'\n");
    }
}

// it seeams that we found a line for the searched system, try to guess the video filename...
$videoName = getVideoName($elements->item(0));
if(is_null($videoName)) {
    die("found entry which is not an image ! Canceled...\n");
}

// try to get the video with the guessed filename and save it to a local file...
$videoUrl = getVideoPath("$SYSTEM_TO_SEARCH", $videoName);
saveVideoToFile($videoUrl, $FILENAME_TO_WRITE);



/**************************************************************************************************
 *
 * Helper functions
 *
 *************************************************************************************************/

 /**
  * build anurl that should give an HTML page with the searched game for all systems
  */
function getSearchUrl($gameName) {

    return SEARCH_URL_BASE . urlencode($gameName);
}

/**
  * retreive the HTML from a GET HTTP request
  */
function getHtmlString($url) {
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    return curl_exec($ch);
}

/**
  * simple wraper to build a guessed video file path
  */
function getVideoPath($system, $videoName) {

    return sprintf(VIDEO_PATH_BASE, $system, $videoName);
}

/**
  * search an html string for a img or embed tag which src or flashvars attribute contains the system we are searching a video for
  */
function searchForEntry($system, $html) {

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->recover = true;
    $dom->strictErrorChecking = false;
    $dom->loadHTML($html);

    $xpath = new DOMXpath($dom);
    
    return $xpath->query("//img[contains(@src,'$system')]");
}


/**
  * guess a video name based on the img or embed element of the searched system
  */
function getVideoName($element) {
    
    if($element->nodeName != "img" && $element->nodeName != "embed") return null;
    
    $filename = basename($element->getAttribute('src'),IMG_EXTENSION);
    $videoFilename = str_replace(Array('Thumb_','list_','-','.',',','!'),Array('','','_'),$filename);
    
    return $videoFilename.VIDEO_EXTENSION;
}

/**
  * try to get the video and save it to a local file
  */
function saveVideoToFile($url, $file) {
    
    file_put_contents($file, fopen($url, 'r'));
}

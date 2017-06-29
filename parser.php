<?php
/* @Author   : julien Tiphaine (www.bluemind.org)
 * @Date     : 15.05.2017 
 *
 * This script is used to parse folder of roms under subfolders emulationstation format.
 * It uses scraped images to find games name and scrap video using scraper.php script.
 *
 * This script can be used to resize and change frame rate of scraped video using ffmpeg
 *
 * Usage example:
 * - to scrap videos and convert them in 30 Fps, 320px width
 *      php parser.php gamelists downloaded_images 30 320
 * - to scrap video with no convertion:
 *      php parser.php gamelists downloaded_images
 *
 *  where :
 *      - "gamelists" is the emulationstation's directory where all gamelist XML files are stored (in system subfolders)
 *      - downloaded_images is the target directory where to store videos (in system subfolders)
 */


/**************************************************************************************************
 *
 * Settings
 *
 *************************************************************************************************/
define("SCRAPER_CMD", "php scraper.php \"%s\" %s \"%s\"");
define("VIDEO_CMD"  , "ffmpeg -nostats -loglevel panic -hide_banner -i \"%s\" -r %s -filter:v scale=%s:-1 \"%s\"");
define("TMP_FILE"   , "tmpvideo.mp4");

/**
 * Array that map emulationstation system name => gamesdatabase.org system name
 * To find emulationstation names, go to "Recommended theme" section of http://www.emulationstation.org/gettingstarted.html
 * To find gamesdatabase system name, go to http://www.gamesdatabase.org/systems and replace spaces by unerscore
 *    e.g. for "Sega 32X", the system name is "Sega_32X"
*/
$SYSTEM_MAP = Array (   "32x"               => "Sega_32X",
                        "dreamcast"         => "Sega_Dreamcast",
                        "gba"               => "Nintendo_Game_Boy_Advance",
                        "gbc"               => "Nintendo_Game_Boy_Color",
                        "gamegear"          => "Sega_Game_Gear",
                        "genesis"           => "Sega_Genesis",
                        "segacd"            => "Sega_CD",
                        "n64"               => "Nintendo_N64",
                        "nes"               => "Nintendo_NES",
                        "pcengine"          => "NEC_PC_Engine",
                        "pce-cd"            => "NEC_TurboGrafx_CD",
                        "psx"               => "Sony_Playstation",
                        "mastersystem"      => "Sega_Master_System",
                        "snes"              => "Nintendo_SNES",
                        "fba"               => "Arcade",
			"ngpc"		    => "SNK_Neo-Geo_Pocket_Color"
                    );


// handle command line arguments
$DIR_TO_SCAN        = null;
$VIDEO_DIR          = null;
$VIDEO_FRAMERATE    = null;
$VIDEO_WIDTH        = null;


if($argc >= 3) {
    $DIR_TO_SCAN = $argv[1];
    $VIDEO_DIR   = $argv[2];
    
    if($argc == 5) {
        $VIDEO_FRAMERATE= $argv[3];
        $VIDEO_WIDTH    = $argv[4];
    }
} else {
    die("usage : parser.php <dir to scan> <video directory> [framerate <width>]");
}


// start scanning emulationstation images dir
foreach(glob($DIR_TO_SCAN."/*",GLOB_ONLYDIR) as $dirname) {

    $systemName = basename($dirname);
    $gamedbFile = $dirname . "/gamelist.xml";
    $nbGames    = 0;
    $count      = 0;
    
    if(!array_key_exists($systemName, $SYSTEM_MAP)) {
        echo "unsupported system '$systemName' => skipped\n";
        continue;
    }
    
    echo "starting to parse $systemName ...\n";
    
    // Load XML DB File
    echo "  loading " . $gamedbFile . "\n";
    $dom = loadGameDb($gamedbFile);
    if($dom === null) {
        echo "  error loading XML => Skipping whole system\n";
        continue;
    }
    
    // Get games elements to iterate
    $xpath = new DOMXpath($dom);
    $gameElements = $xpath->query("//game");
    $nbGames = $gameElements->length;
    if (is_null($gameElements) || $nbGames == 0) {
        echo " no game found => Skipping whole system\n";
        continue;
    }

    echo "  found $nbGames game(s)\n";
    
    // start searching videos for each game
    foreach($gameElements as $gameElement) {
        
        $videoFileName  = getVideoFilenameFromImage($gameElement->getElementsByTagName('image')->item(0)->nodeValue);
        $videoFilePath  = getVideoFilePathFromImage($gameElement->getElementsByTagName('image')->item(0)->nodeValue);
        $targetPath     = $VIDEO_DIR . '/' . $systemName;
        $gameName       = $gameElement->getElementsByTagName('name')->item(0)->nodeValue;
        $count++;
    
        echo "  ($count/$nbGames)  $gameName ... ";
        
        if(file_exists($targetPath . '/' . $videoFileName)) {
            echo "video file already found => skipped\n";
            continue;
        }
        
        echo "downloading... ";
        if($VIDEO_FRAMERATE!=null && $VIDEO_WIDTH!=null) {
            $tmpfile = $targetPath.'/'.TMP_FILE;
            
            system(sprintf(SCRAPER_CMD,$gameName,$SYSTEM_MAP[$systemName],$tmpfile));
            if(!file_exists($tmpfile)) {
                echo " => skipped\n";
                continue;
            }
            
            echo "converting... ";
            system(sprintf(VIDEO_CMD,$tmpfile,$VIDEO_FRAMERATE,$VIDEO_WIDTH, $targetPath . '/' . $videoFileName));
            
            @unlink($tmpfile);
        } else {
            system(sprintf(SCRAPER_CMD,$gameName,$SYSTEM_MAP[$systemName],$targetPath . '/' . $videoFileName));
        }
        
        
        if(file_exists($targetPath . '/' . $videoFileName)) {
            echo " DONE !";
            echo " Video tag: "; 
            
            $videoElement = $gameElement->getElementsByTagName("video");
            $imageElement = $gameElement->getElementsByTagName('image');
            
            if($videoElement->length == 0) {
                $videoElement = $dom->createElement('video',htmlspecialchars($videoFilePath));
                $gameElement->insertBefore($videoElement,$imageElement->item(0));
                echo "added\n";
            } else {
                echo "updated\n";
                $videoElement->item(0)->nodeValue = $videoFilePath;
            }
            
            
        } else {
            echo " ERROR !\n";
        }
    }

    file_put_contents($dirname . "/gamelist.xml", $dom->saveXML());
    echo "gamelist.xml written\n\n";
}


/**************************************************************************************************
 *
 * Helper functions
 *
 *************************************************************************************************/
function loadGameDb($xmlFilePath) {
    
    $dom = new DOMDocument();
    $dom->formatOutput = true;
    $dom->preserveWhiteSpace = true;
    
    if($dom->load($xmlFilePath)) {
        return $dom;
    } else {
        return null;
    }
}
 
function getGameNameFromFile($filename) {
    
    $filename = str_replace('-image', '', $filename);
    
    $end = strpos($filename, '(');
    if($end === false) {
        $end = strpos($filename, '[');
        if($end === false) {
            $end = strpos($filename, '.');
        }
    }
    
    return str_replace(Array("_",".bin",".smc",".smd"),Array(" ",""), (substr($filename,0,$end)));
}


function getVideoFilenameFromImage($filePath) {
    return getVideoFilePathFromImage(basename($filePath));
}

function getVideoFilePathFromImage($filePath) {
    return str_replace(Array('image.jpg','image.png'),'video.mp4',$filePath);
}

# gamesdatabase_scraper
In short: get video previews for emulationstation ;)


This is a quick & dirty PHP script used to parse folder of roms under subfolders in emulationstation format.
It uses already scraped roms to read game's names and scrap video using scraper.php script.

It is possible to resize and change frame rate of scraped video (need ffmpeg to be installed).

Usage example:
 - to scrap videos and convert them in 30 Fps, 320px width
      php parser.php gamelists downloaded_images 30 320
 - to scrap video with no convertion:
      php parser.php gamelists downloaded_images

  where :
    - "gamelists" is the emulationstation's directory where all gamelist XML files are stored (in system subfolders)
    - downloaded_images is the target directory where to store videos (in system subfolders)

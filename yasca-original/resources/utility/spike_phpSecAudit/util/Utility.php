<?php
    /*
    *  $Id: Utility.php 27242 2005-07-21 01:21:42Z hkodungallur $
    *  
    *  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
    *  Licensed under the Open Source License version 2.1
    *  (See http://www.spikesource.com/license.html)
    */
?>
<?php

    /** 
    * Utility functions 
    * 
    * @author Nimish Pachapurkar <npac@spikesource.com>
    * @version $Revision: $
    * @package SpikePHPCheckstyle_Util
    */
    class Utility 
    {

        /*{{{ public function getTimeStamp() */

        /** 
        * Return the current timestamp in human readable format.
        * Thursday March 17, 2005 19:10:47
        * 
        * @return Readable timestamp
        * @access public
        */
        public function getTimeStamp() 
        {
            $ts = getdate();
            return $ts["weekday"] . " " . $ts["month"] . " " . $ts["mday"] 
            . ", " . $ts["year"] . " " 
            . sprintf("%02d:%02d:%02d", $ts["hours"], $ts["minutes"], $ts["seconds"]);
        }

        /*}}}*/
        /*{{{ public function shortenFilename() */

        /** 
        * Shorten the filename to some maximum characters 
        * 
        * @param $filename Complete file path
        * @param $maxlength=150 Maximum allowable length of the shortened 
        * filepath
        * @return Shortened file path
        * @access public
        */
        public function shortenFilename($filename, $maxlength = 80) 
        {
            $length = strlen($filename);
            if ($length < $maxlength) {
                return $filename;
            }

            // trim the first few characters
            $filename = substr($filename, $length - $maxlength);
            // If there is a path separator slash in first n characters,
            // trim upto that point.
            $n = 20;
            $firstSlash = strpos($filename, "/");
            if ($firstSlash === false || $firstSlash > $n) {
                $firstSlash = strpos($filename, "\\");
                if ($firstSlash === false || $firstSlash > $n) {
                    return "..." . $filename;
                }
                return "..." . substr($filename, $firstSlash);
            }
            return "..." . substr($filename, $firstSlash);
        }

        /*}}}*/
        /*{{{ public function unixifyPath() */

        /** 
        * Convert Windows paths to Unix paths 
        * 
        * @param $path File path
        * @return String Unixified file path
        * @access public
        */
        public function unixifyPath($path) 
        {
            // Remove the drive-letter:
            if (strpos($path, ":") == 1) {
                $path = substr($path, 2);
            }
            $path = $this->replaceBackslashes($path);
            return $path;
        }

        /*}}}*/
        /*{{{ public function replaceBackslashes() */

        /** 
        * Convert the back slash path separators with forward slashes. 
        * 
        * @param $path Windows path with backslash path separators
        * @return String Path with back slashes replaced with forward slashes.
        * @access public
        */
        public function replaceBackslashes($path) 
        {
            $path = str_replace("\\", "/", $path);
            return $this->capitalizeDriveLetter($path);
        }
        /*}}}*/
        /*{{{ public function capitalizeDriveLetter() */

        /** 
         * Convert the drive letter to upper case
         * 
         * @param $path Windows path with "c:<blah>"
         * @return String Path with driver letter capitalized.
         * @access public
         */
        public function capitalizeDriveLetter($path) 
        {
            if (strpos($path, ":") === 1) {
                $path = strtoupper(substr($path, 0, 1)) . substr($path, 1);
            }
            return $path;
        }

        /*}}}*/

        /** 
         * Make directory recursively. 
         * (Taken from: http://aidan.dotgeek.org/lib/?file=function.mkdirr.php)
         * 
         * @param $dir Directory path to create
         * @param $mode=0755 
         * @return True on success, False on failure
         * @access public
         */
        public function makeDirRecursive($dir, $mode = 0755) 
        {
            // Check if directory already exists
            if (is_dir($dir) || empty($dir)) {
                return true;
            }

            // Ensure a file does not already exist with the same name
            if (is_file($dir)) {
                error_log("File already exists: " . $dir);
                return false;
            }

            $dir = $this->replaceBackslashes($dir);

            // Crawl up the directory tree
            $next_pathname = substr($dir, 0, strrpos($dir, "/"));
            if ($this->makeDirRecursive($next_pathname, $mode)) {
                if (!file_exists($dir)) {
                    return mkdir($dir, $mode);
                }
            }

            return false;
        }

        /**
         * Delete the contents of an entire directory, and (optional)
         * delete the directory itself. This function was deemed necessary
         * since the PHPCheckstyle output folder must be empty before the 
         * creation of new files. Otherwise, there might be some unfavorable
         * merging of corresponding files and such.
         *
         * @param $dir directory path to wipe
         * @param $flag whether to delete directory itself
         * @return nothing
         * @access public
         */
        public function wipeDir($dir, $itself=false)
        {
            // delete contents of directory, before deciding whether to 
            // delete the directory itself
            if ($root = @opendir($dir)) {
                while ($file = readdir($root)) {
                    if ($file == "."  || $file == "..") {
                        continue; 
                    }
                    $fullPath = $dir . "/" . $file;
                    // if dir, delete dir contents and dir itself
                    // if file, delete the file
                    if (is_dir($fullPath)) {
                        $this->wipeDir($fullPath, true);
                    } else {
                        unlink($fullPath);
                    }
                }
            }
            // once contents have been wiped, if $itself, remove dir also
            if ($itself) {
                rmdir($dir);
            }
        }

        // {{{ private function _getAllPhpFiles

        /**
         * got through a directory recursively and get all the
         * php (with extension .php and .tpl) files
         * Ignores files or subdirectories that are in the $excludes
         *
         * @param $src source directory
         * @return an array of files that end with .php or .tpl
         * @access public
         */
        public function getAllPhpFiles($src, $excludes)
        {
            $files[] = array();
            if (!is_dir($src)) {
                $files[] = $src;
            } else {
                if ($root = @opendir($src)) {
                    while ($file = readdir($root)) {
                        if ($file == "." || $file == "..") {
                            continue;
                        }
                        $fullPath = $src . "/" . $file;
                        $relPath =  substr($fullPath, strlen($src) + 1);
                        if (!in_array($relPath, $excludes)) {
                            if (is_dir($src . "/" . $file)) {
                                $files = array_merge($files, $this->getAllPhpFiles($src . "/" . $file, $excludes));
                            } else {
                                $pathParts = pathinfo($file);
                                if (array_key_exists('extension', $pathParts)) {
                                    if (in_array($pathParts['extension'], array("php", "tpl"))) {
                                        $files[] = $src . "/" . $file;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $files;
        }


        /** 
         * Copy a file, or recursively copy a folder and its contents 
         * This function is taken from the following website:
         *   http://aidan.dotgeek.org/lib/?file=function.copyr.php
         * 
         * @author      Aidan Lister <aidan@php.net> 
         * @version     1.0.1 
         * @param       string   $source    Source path 
         * @param       string   $dest      Destination path 
         * @return      bool     Returns TRUE on success, FALSE on failure 
         */ 
        public function copyr($source, $dest) 
        { 
            // Simple copy for a file 
            if (is_file($source)) { 
                return copy($source, $dest); 
            } 
            
            // Make destination directory 
            if (!is_dir($dest)) { 
                mkdir($dest); 
            } 
            
            // Loop through the folder 
            $dir = dir($source); 
            while (false !== $entry = $dir->read()) { 
                // Skip pointers 
                if ($entry == '.' || $entry == '..') { 
                    continue; 
                } 
                
                // Deep copy directories 
                if ($dest !== "$source/$entry") { 
                    $this->copyr("$source/$entry", "$dest/$entry"); 
                } 
           } 
           
           // Clean up 
           $dir->close(); 
           return true; 
       }
    }

    $util = new Utility();
    global $util;
?>

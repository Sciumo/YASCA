<?php

/**
 * This class looks for all URLs located in the source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_URLFinder extends Plugin {
    public $valid_file_types = array();
    public $invalid_file_types = array("jar", "exe", "zip", "war", "tar", "ear", "lib", "dll");
    
    private static $CACHE_ID = 'Plugin_URLFinder.url_list,Unique URLs';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        $url_list = isset($yasca->general_cache[Plugin_URLFinder::$CACHE_ID]) ? 
                          $yasca->general_cache[Plugin_URLFinder::$CACHE_ID] : 
                          array();
        
        $matches = preg_grep('/([a-z0-9\-\_][a-z0-9\-\_\.]+\.com)[^a-z0-9]/i', $this->file_contents);
        foreach ($matches as $match) {
            preg_match('/([a-z0-9\-\_][a-z0-9\-\_\.]+\.com)[^a-z0-9]/i', $match, $submatches);
            $url = $submatches[1];                          // only have to worry about [1]
            if (preg_match('/package /', $url)) continue;
            if (preg_match('/import /', $url)) continue;
            if (preg_match('/^\s*\*/', $url)) continue;     // probably in a comment
    
            $value = "$url,$this->filename";
            if (in_array($value, $url_list)) continue;
            array_push($url_list, $value);
        }
        sort($url_list);
        
        $yasca->general_cache[Plugin_URLFinder::$CACHE_ID] = $url_list;
        $yasca->add_attachment(Plugin_URLFinder::$CACHE_ID);
                
        $yasca->register_callback('post-scan', array(get_class($this), 'report_callback'));
        
        $matchs = null;
        $url_list = null;
    }
    
    /**
     * Callback function for turning the URL list into an HTML table.
     */
    public static function report_callback() {
        $yasca =& Yasca::getInstance(); 
        $url_list = isset($yasca->general_cache[Plugin_URLFinder::$CACHE_ID]) ? 
                          $yasca->general_cache[Plugin_URLFinder::$CACHE_ID] : 
                          array();

        $html = '<table style="width:95%;">';
        $html .= '<th>URL</th><th>Filename</th>';
        foreach ($url_list as $url) {
            $url_array = explode(",", $url, 2);
            $html .= "<tr><td>" . $url_array[0] . "</td><td><a target=\"_blank\" href=\"file://" . $url_array[1] . "\">" . $url_array[1] . "</a></td></tr>";
        }
        $html .= "</table>";
        
        $yasca->general_cache[Plugin_URLFinder::$CACHE_ID] = $html; 
    }
}
?>
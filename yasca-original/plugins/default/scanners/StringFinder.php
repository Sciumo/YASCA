<?php

/**
 * This class looks for all strings located in the source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_StringFinder extends Plugin {
    public $valid_file_types = array();
    public $invalid_file_types = array("jar", "exe", "zip", "war", "tar", "ear", "lib", "dll", "doc");
    
    private static $CACHE_ID = 'Plugin_StringFinder.string_list,Unique Strings';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        $string_list = isset($yasca->general_cache[Plugin_StringFinder::$CACHE_ID]) ? 
                             $yasca->general_cache[Plugin_StringFinder::$CACHE_ID] : 
                             array();
        
        $matches = preg_grep('/\"([^\"]+)\"/', $this->file_contents);
        foreach ($matches as $match) {
            preg_match('/\"([^\"]+)\"/', $match, $submatches);
            $str = $submatches[1];
            if (preg_match('/[^\x20-\x7F]/', $str)) continue;
            $str = htmlentities($str);
            if (in_array($str, $string_list)) continue;
            array_push($string_list, $str);
        }
        sort($string_list);
        
        $yasca->general_cache[Plugin_StringFinder::$CACHE_ID] = $string_list;
        $yasca->add_attachment(Plugin_StringFinder::$CACHE_ID);

        $matches = null;
        $string_list = null;
    }
}
?>
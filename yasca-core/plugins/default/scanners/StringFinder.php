<?php

/**
 * This class looks for all strings located in the source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_StringFinder extends Plugin {
    public $valid_file_types = array();
    
	public function Plugin_StringFinder($filename, &$file_contents){
		parent::Plugin($filename, $file_contents);
		// Handle this separately, since it's valid on all files EXCEPT those listed below
		// TODO White-list checking instead of blacklist checking. An 80 meg media file in the directory will crash yasca.
		if ($this->check_in_filetype($filename, array("jar", "zip", "dll", "war", "tar", "ear",
													  "jpg", "png", "gif", "exe", "bin", "lib",
													  "svn-base", "7z", "rar", "gz",
													  "mov", "wmv", "mp3"))) {
			$this->is_valid_filetype = false;
		}
	}
    
    protected static $CACHE_ID = 'Plugin_StringFinder.string_list,Unique Strings';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        if (!isset($yasca->general_cache[self::$CACHE_ID])){ 
        	$yasca->general_cache[self::$CACHE_ID] = array();
        	$yasca->add_attachment(self::$CACHE_ID);
        }
        
        $cache =& $yasca->general_cache[self::$CACHE_ID];
               
        $matches = preg_grep('/(\"([^\"]+?)\"|\'([^\']+?)\')/', $this->file_contents);
        $matches = preg_grep('/[^\x20-\x7F]/', $matches, PREG_GREP_INVERT);
        foreach ($matches as $match) {
            preg_match_all('/(\"([^\"]+?)\"|\'([^\']+?)\')/', $match, $submatches);
            $submatches = $submatches[3]; //The other submatches array elements have cruft around them
            foreach ($submatches as $str){
	            if (isset($cache[$str])) continue;
	            $cache[$str] = true;
            }
        }
        
        
        static $already_added = false;
    	if ($already_added) return;
    	$already_added = true;
    	
        $id = self::$CACHE_ID;
        $yasca->register_callback('post-scan', function () use ($id) {
        	$yasca =& Yasca::getInstance();
        	$strings = array_keys($yasca->general_cache[$id]);
        	//This is likely a massive array, so clear out the cache'd array early so we don't choke php
        	unset($yasca->general_cache[$id]);
        	array_walk($strings, function (&$line) {
        		$line = htmlentities($line);
        	});
        	sort($strings);
        	$yasca->general_cache[$id] = $strings;
   	 	});
        
    }
}
?>
<?php

/**
 * This class looks for all URLs located in the source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_URLFinder extends Plugin {
    public $valid_file_types = array();
    
	public function Plugin_URLFinder($filename, &$file_contents){
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
    
    protected static $CACHE_ID = 'Plugin_URLFinder.url_list,Unique URLs';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        if (!isset($yasca->general_cache[self::$CACHE_ID])){ 
        	$yasca->general_cache[self::$CACHE_ID] = array();
        	$yasca->add_attachment(self::$CACHE_ID);
        }
        
        $cache =& $yasca->general_cache[self::$CACHE_ID];
        
        $matches = preg_grep('/([a-z0-9\-\_][a-z0-9\-\_\.]+\.(com|org|gov|biz|edu|eu|info|cn|us))[^a-z0-9]/i', $this->file_contents);
        $matches = preg_grep('/(package |import )/', $matches, PREG_GREP_INVERT);
        $matches = preg_grep('/^\s*\*/', $matches, PREG_GREP_INVERT);     // probably in a comment
        foreach ($matches as $match) {
            preg_match_all('/([a-z0-9\-\_][a-z0-9\-\_\.]+\.(com|org|gov|biz|edu|eu|info|cn|us))[^a-z0-9]/i', $match, $submatches);
            $submatches = $submatches[1];	//The submatches[0] block have cruft around them
            foreach ($submatches as $url){
	            if (isset($cache[$url])) continue;
	            $cache[$url] = str_replace($yasca->options['dir'], "", correct_slashes($this->filename));
            }
            
        }
        
        static $already_added = false;
        if ($already_added) return;
        $already_added = true;
        
        $id = self::$CACHE_ID;
        $yasca->register_callback('post-scan', function () use ($id) {
        	$yasca =& Yasca::getInstance();
        	asort($yasca->general_cache[$id]);
	
	        $html = '<table style="width:99%;">';
	        $html .= '<th>URLs to this host</th><th>Exist in at least this file</th>';
	        foreach ($yasca->general_cache[$id] as $url => $file) {
	            $html .= "<tr>";
	    		$html .= "<td>$url</td>";
	           	$html .= "<td><a target=\"_blank\" ";
		        $html .= "href=\"file://$file\">$file</a></td>";
		        $html .= "</tr>";
	        }
	        $html .= "</table>";
	        $yasca->general_cache[$id] = $html; 
   	 	});
    }
    
}
?>
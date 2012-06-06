<?php
//If Grep.php is to be removed from the package, copy/paste it's contents to the end of this file
//and remove the next line.
require_once("plugins/Grep.php");

require_once("lib/common.php");
require_once("lib/Plugin.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");

/**
 * This attachment attempts to flag locations in code that should
 * be inspected manually.
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_PotentialConcerns extends Plugin_Grep {
	
	/**
	 * Redefined, using static late binding.
	 * The union of all valid file types accepted by the grep sub-plugins
	 * @var array of strings
	 */
	protected static $union_of_valid_file_types;
	
	/**
	 * Redefined, using static late binding.
	 * @var array of Grep_File()s
	 */
	protected static $grep_files;
	
    protected static $concerns_dir = 'plugins/default/scanners/concerns/';

    protected static $CACHE_ID = 'Plugin_PotentialConcerns.potential_concerns,Potential Concerns';
    
	protected static function load_grep_files(){
		static::$grep_files = array();
		$yasca =& Yasca::getInstance();
		foreach (static::concerns_list() as $plugin){
			if (!endsWith($plugin, ".grep"))
				continue;
			
			$grep_content = file($plugin, FILE_TEXT+FILE_IGNORE_NEW_LINES);
			if (!array_any($grep_content, function($line) {return preg_match('/^\s*grep\s*=\s*(.*)/i', $line);}))
				continue;
				
			if (!array_any($grep_content, function($line) {return preg_match('/^\s*category\s*=\s*(.*)/i', $line);}))
				continue;

			//Code below this line means it's a keeper plugin.
			array_walk($grep_content, function (&$line) {
				$line = trim($line);
			});
			static::$grep_files[] = new Grep_File($plugin, $grep_content);
		}
	}
    
    protected static function concerns_list() {
    	$concerns = array();

		if ($handle = opendir(static::$concerns_dir)) {
		    while (false !== ($file = readdir($handle))) {
		    	if ($file == "." || $file == "..") continue;
				if (is_file(static::$concerns_dir . $file)) {
				    $concerns[] = static::$concerns_dir . $file;
				}
		    }
	
		    closedir($handle);
		}

		return $concerns;
    }


    public function execute() {
    	parent::execute();
    	
		$yasca =& Yasca::getInstance();
		
		if (!isset($yasca->general_cache[static::$CACHE_ID])){ 
        	$yasca->general_cache[static::$CACHE_ID] = array();
        	$yasca->add_attachment(static::$CACHE_ID);
        }
        
        $yasca->general_cache[static::$CACHE_ID] = array_merge($yasca->general_cache[static::$CACHE_ID],
        	$this->result_list);
        	
        //Do not let the results reach the main report.
        $this->result_list = array();
        
        static $already_added = false;
    	if ($already_added) return;
    	$already_added = true;
    	
        $id = static::$CACHE_ID;
        $yasca->register_callback('post-scan', function () use ($id) {
        	$yasca =& Yasca::getInstance();
        	$results = $yasca->general_cache[$id];
        	//This is likely a massive array, so clear out the cache'd array early so we don't choke php
        	$yasca->general_cache[$id] = null;  
        	
            //Filter out the full pathnames
			foreach ($yasca->results as &$result){
				$result->filename = str_replace($yasca->options['dir'], "", correct_slashes($result->filename));
			}
			
        	//Filter out the unique results
        	$results = array_unique_with_selector($results, function($result){
        		return "$result->filename->$result->line_number->$result->category->$result->severity->$result->source";
        	});
        	
        	$yasca->general_cache[$id] = array_reduce($results, function ($acc, $result){
	    		$acc = "$acc <a title=\"\" target=\"_blank\" ";
	    		$acc .= "href=\"file://$result->filename\" source_code_link=\"true\">";
	    		$acc .= basename($result->filename);
	    		$acc .= ":$result->line_number</a> " . htmlentities($result->source) . " $result->description <br/><br/>\n";
	    		return $acc;
	    	});
   	 	});
    }
}
?>
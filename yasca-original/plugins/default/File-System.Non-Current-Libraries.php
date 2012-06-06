<?php

/**
 * This class looks for library files (.jar, .so, .dll) that are not of the latest
 * version, or not known at all. Uses resources/current_libraries/*.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_file_system_non_current_libraries extends Plugin {
    public $valid_file_types = array("jar", "so", "dll");
    
    private $library_cache; 
    
    /**
     * Executes the plugin to scan a particular file. Uses a cache to speed things up.
     */
    function execute() {
        $yasca =& Yasca::getInstance();
        $cache_name = get_class($this) . ".library_cache";
        
        if (!isset($yasca->general_cache[$cache_name])) {
            $yasca->log_message("Initializing library cache in " . get_class($this), E_USER_NOTICE);
            $yasca->general_cache[$cache_name] = array();
            $this->initialize_cache($yasca->general_cache[$cache_name]);
        }   
        $library_cache =& $yasca->general_cache[$cache_name];
        $filename = basename($this->filename);
        if (!isset($library_cache[$filename])) { 
            $result = new Result();
            $result->line_number = 0;
            $result->is_source_code = false;
            
            $similar_jar = find_similar_text(array_keys($library_cache), $filename, 4);
            if ($similar_jar !== false) {
                $result->source = "Similar library: $similar_jar";
            } else {
                $result->source = "Unknown or non-standard library.";
            }
            
            $result->severity = 4;
            $result->category = "Unknown, Non-Standard, or Obsolete Library Used";
            $result->description = <<<END
            <p>
                Applications should generally use the latest stable release of third-party
                libraries. A false-positive finding could occur if the libraries are not
                included in any of the files located in <b>resources/current_libraries/</b>.
                <br/><br/>
                Note that this plugin only uses the <i>filename</i> of the library, rather
                than anything more intellgent.
            </p>
            <p>
                <h4>References</h4>
                <ul>
                    <li><a href="http://jakarta.apache.com/">Jakarta</a></li>
                </ul>
            </p>
END;
            array_push($this->result_list, $result);
        }
        
        
        
    }
    
    /**
     * This function initializes the cache from a text file that was presumably loaded
     * by a separate process. The file contains a list of jar files that are considered
     * "current". Any jar file that is not in that list is considered non-standard or
     * non-current.
     * @param array $cache cache array to load data into
     */
    function initialize_cache(&$cache) {
        $yasca =& Yasca::getInstance();
        foreach ($yasca->dir_recursive("resources/current_libraries") as $library) {
            if (startsWith($library, "_")) continue;
            $data = explode("\r\n", file_get_contents($library));
            foreach ($data as $lib) {
                $cache[$lib] = "";
            }
        }
        return $cache;
    }
}
?>

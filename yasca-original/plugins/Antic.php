<?php

/**
 * The Antic Plugin uses Antic to discover potential vulnerabilities in Java or C/C++ files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Antic extends Plugin {
    public $valid_file_types = array("java", "c", "cpp", "h");

    public $is_multi_target = true;
    
    function execute() {
        if (getSystemOS() !== 'Windows') return;        // Only execute on Windows
        static $alreadyExecuted;
        if ($alreadyExecuted == 1) return;
        $alreadyExecuted = 1;
        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];
        $antic_results = array();
        
        $yasca->log_message("Forking external process (antiC)...", E_USER_WARNING);
        exec( "resources\\utility\\antic.exe -java " . escapeshellarg($dir), $antic_results);
        $yasca->log_message("External process completed...", E_USER_WARNING);
        
        if ($yasca->options['debug']) 
            $yasca->log_message("antiC returned: " . implode("\r\n", $antic_results), E_ALL);
        
        foreach ($antic_results as $antic_result) {
            $matches = array();
            if (preg_match("/^([a-z]:[^:]+):(\d+):(\d+): (.*)/i", $antic_result, $matches)) {
                $filename = $matches[1];
                $line_number = $matches[2];
                $message = $matches[4];
                
                $result = new Result();
                $result->line_number = $line_number;
                $result->filename = $filename;
                $result->plugin_name = $yasca->get_adjusted_alternate_name("Antic", $message, $message);
                $result->severity = $yasca->get_adjusted_severity($result->plugin_name, $message, 4);
                $result->category = "Antic Finding";
                $result->category_link = "http://artho.com/jlint/";
                $result->source = $yasca->get_adjusted_description($result->plugin_name, $message, $message);
                $result->is_source_code = false;
                $result->source_context = array_slice( file($filename), max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                array_push($this->result_list, $result);
            }
        }   
    }   
}
?>

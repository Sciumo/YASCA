<?php

/**
 * The JLint Plugin uses JLint to discover potential vulnerabilities in .class files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_JLint extends Plugin {
    public $valid_file_types = array();

    public $is_multi_target = true;
    
    function execute() {
        if (getSystemOS() !== 'Windows') return;        // Only execute on Windows
        
        static $alreadyExecuted;
        if ($alreadyExecuted == 1) return;
        $alreadyExecuted = 1;
        
        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];
        $jlint_results = array();
        
        // Try to execute using native binary of via wine, if possible
        if (getSystemOS() == "Windows") {
            $yasca->log_message("Forking external process (JLint)...", E_USER_WARNING);
            exec( "resources\\utility\\jlint.exe -source " . escapeshellarg($dir) . " " . escapeshellarg($dir), $jlint_results);
            $yasca->log_message("External process completed...", E_USER_WARNING);
        } else if (getSystemOS() == "Linux") {
            if (preg_match("/no wine in/", `which wine`)) {
                $yasca->log_message("No JLint executable and wine not found.", E_ALL);
                return;
            } else {
                $yasca->log_message("Forking external process (JLint)...", E_USER_WARNING);
                exec( "wine resources/utility/jlint.exe -source " . escapeshellarg($dir) . " " . escapeshellarg($dir), $jlint_results);
                $yasca->log_message("External process completed...", E_USER_WARNING);
            }
        }

        if ($yasca->options['debug']) 
            $yasca->log_message("JLint returned: " . implode("\r\n", $jlint_results), E_ALL);
        
        $iteration = 1;
        $debug_mode = true;
        foreach ($jlint_results as $jlint_result) {
            $matches = array();
            if (preg_match("/^([a-z]:[^:]+):([^:]): (.*)/i", $jlint_result, $matches)) {
                $filename = $matches[1];
                $line_number = is_numeric($matches[2]) ? $matches[2] : "0";
                $message = $matches[3];
        
                if ($line_number == 1 && $iteration++ == 1) {
                    $debug_mode = false;
                }
                            
                $result = new Result();
                $result->line_number = ($debug_mode ? $line_number : 0);
                $result->filename = $filename;
                $result->plugin_name = $yasca->get_adjusted_alternate_name("JLint", $message, $message);
                $result->severity = $yasca->get_adjusted_severity("JLint", $message, 4);
                $result->category = "JLint Finding";
                $result->category_link = "http://artho.com/jlint/";
                $result->source = $yasca->get_adjusted_description("JLint", $message, $message);
                $result->is_source_code = false;
                $result->source_context = "";
                array_push($this->result_list, $result);
            }
        }   
    }   
}
?>

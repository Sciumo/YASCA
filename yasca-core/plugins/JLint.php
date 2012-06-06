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

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\jlint\\jlint.exe",
                               'Linux'   => "%SA_HOME%resources/utility/jlint/jlint");  
    
        
    public $installation_marker = "jlint";
    
    protected static $already_executed = false;
    
    private $USE_WINE = false;
    
    
	public function __construct($filename, $file_contents){
		if (static::$already_executed){
			$this->initialized = true;
			return;
		}
		parent::__construct($filename,$file_contents);
	}
	
    function execute() {
        if (static::$already_executed) return;  
        static::$already_executed = true;  
            
        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];
        $jlint_results = array();
        
        $executable = $this->executable[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);
        
        // Try to execute using native binary of via wine, if possible
        if (getSystemOS() == "Windows") {
            $yasca->log_message("Forking external process (JLint)...", E_USER_WARNING);
            exec( $executable . " -source " . escapeshellarg($dir) . " " . escapeshellarg($dir) . " 2>NUL", $jlint_results);
            $yasca->log_message("External process completed...", E_USER_WARNING);
        } else if (getSystemOS() == "Linux") {
            if ($this->USE_WINE) {
                $wine_arr = array();
                $wine_errorlevel = 0;
                exec("which wine", $wine_arr, $wine_errorlevel);
            
                if (preg_match("/no wine in/", implode(" ", $wine_arr)) || $wine_errorlevel == 1) {
                    $yasca->log_message("No Linux \"JLint\" executable and wine not found.", E_ALL);
                    return;
                } else {
                    $yasca->log_message("Forking external process (JLint)...", E_USER_WARNING);
                    exec( "wine " . $executable . " -source " . escapeshellarg($dir) . " " . escapeshellarg($dir) . " 2>/dev/null", $jlint_results);
                    $yasca->log_message("External process completed...", E_USER_WARNING);
                }
            } else {
                $yasca->log_message("Forking external process (JLint)...", E_USER_WARNING);
                exec( $executable . " -source " . escapeshellarg($dir) . " " . escapeshellarg($dir) . " 2>/dev/null", $jlint_results);
                $yasca->log_message("External process completed...", E_USER_WARNING);
            }
        }

        if ($yasca->options['debug']) 
            $yasca->log_message("JLint returned: " . implode("\r\n", $jlint_results), E_ALL);
        
        $iteration = 1;
        $debug_mode = true;
        foreach ($jlint_results as $jlint_result) {
            $matches = explode(":", $jlint_result, 3);
            if (count($matches) !== 3) continue;
            
            $filename = $matches[0];
            $line_number = is_numeric($matches[1]) ? $matches[1] : "0";
            $message = $matches[2];
    
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
?>

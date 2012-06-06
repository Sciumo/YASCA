<?php

/**
 * The JavaScriptLint Plugin uses JavaScript Lint to discover potential bugs or vulnerabilities 
 * JavaScript .js.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_JavaScriptLint extends Plugin {
    public $valid_file_types = array("JAVASCRIPT");

    public $is_multi_target = true;

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\javascriptlint\\jsl.exe",
                               'Linux'   => "%SA_HOME%resources/utility/javascriptlint/jsl");  
    
    public $installation_marker = "javascriptlint";
    
    /** Attempt to use wine to run the Windows version of the executable? */
    private $USE_WINE = false;
    
    protected static $already_executed = false;
    
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
        $jslint_results = array();

        $executable = $this->executable[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);
        $star_suffix = is_dir($dir) ? (getSystemOS() == "Windows" ? "\\*" : "/*") : "";
        
        // Try to execute using native binary or via wine, if possible
        if (getSystemOS() == "Windows") {
            $yasca->log_message("Forking external process (JavaScriptLint)...", E_USER_WARNING);
            exec( $executable . " +recurse -process \"" . addslashes($dir) . $star_suffix . "\" 2>&1", $jslint_results);
            $yasca->log_message("External process completed...", E_USER_WARNING);
        } else if (getSystemOS() == "Linux") {
            if ($this->USE_WINE) {
                $wine_arr = array();
                $wine_errorlevel = 0;
                exec("which wine", $wine_arr, $wine_errorlevel);
            
                if (preg_match("/no wine in/", implode(" ", $wine_arr)) || $wine_errorlevel == 1) {
                    $yasca->log_message("No Linux \"JavaScript Lint\" executable and wine not found.", E_ALL);
                    return;
                } else {
                    $yasca->log_message("Forking external process (JavaScriptLint)...", E_USER_WARNING);
                    exec( "wine " . $executable . " +recurse -process \"" . addslashes($dir) . $star_suffix . "\" 2>&1", $jslint_results);
                    $yasca->log_message("External process completed...", E_USER_WARNING);
                }
            } else {
                $yasca->log_message("Forking external process (JavaScriptLint)...", E_USER_WARNING);
                exec( $executable . " +recurse -process \"" . addslashes($dir) . $star_suffix . "\" 2>&1", $jslint_results);
                $yasca->log_message("External process completed...", E_USER_WARNING);
            }
        }
        
        if ($yasca->options['debug']) 
            $yasca->log_message("JavaScriptLint returned: " . implode("\r\n", $jslint_results), E_ALL);
        
        for ($i=0; $i<count($jslint_results); $i++) {
            $jslint_result = $jslint_results[$i];
            $matches = array();
            if (preg_match("/^(.*)\((\d+)\): (.*): (.*)/", $jslint_result, $matches)) {
                $filename = $matches[1];
                if (!Plugin::check_in_filetype($filename, $this->valid_file_types)) {
                    continue;
                }

                $line_number = $matches[2];
                $severity = $this->convert_to_severity($matches[3], $filename);
                $message = ucwords($matches[4]);
                $description = <<<END
<p>
        This finding was discoverd by JavaScript Lint and is titled:<br/>
    <div style="margin-left:10px;"><strong>{$matches[4]}</strong></div>
</p>
<p>
        <h4>References</h4>
        <ul>
                <li><a href="http://www.javascriptlint.com/">JavaScript Lint Home Page</a></li>
        </ul>
</p>
END;

                if ($message == "Missing Semicolon") $line_number--;
                
                $result = new Result();
                $result->line_number = $line_number;
                $result->filename = $filename;
                $result->plugin_name = $yasca->get_adjusted_alternate_name("JavaScriptLint", $message, $message);
                $result->severity = $yasca->get_adjusted_severity("JavaScriptLint", $message, 4);
                $result->category = "JavaScript Lint Finding";
                $result->category_link = "http://www.javascriptlint.com/";
                $result->source = $yasca->get_adjusted_alternate_name("JavaScriptLint", $message, $message);
                $result->is_source_code = false;

                if (file_exists($filename) && is_readable($filename)) {
                    $t_file = @file($filename);
                    if ($t_file != false && is_array($t_file)) {
                            $result->source_context = array_slice( $t_file, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                    }
                } else {
                    $result->source_context = "";
                }

                $result->description = $description;
                array_push($this->result_list, $result);
            }
        }   
    }
    
    function convert_to_severity($str, $filename) {
        $yasca =& Yasca::getInstance();
        if ($str == "" || $str === false) {
        return 5;   // Default Severity
        }
        $str = str_replace("lint ", "", $str);
        $str = trim($str);
        $str = strtoupper($str);
        switch($str) {
        case "WARNING":
            return 3;
        case "SYNTAXERROR":
            return 1;
        case "CAN'T OPEN FILE":
            return 5;
        default:
            $yasca->log_message("Unexpected severity from JavaScriptLint: [$str $filename]. Treating as informational.", E_USER_WARNING);
            return 5;
        }
    }
        
}
?>

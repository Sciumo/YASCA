<?php

/**
 * The antiC Plugin uses Antic to discover potential vulnerabilities in Java or C/C++ files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Antic extends Plugin {
    public $valid_file_types = array("java", "c", "cpp", "h");

    public $is_multi_target = true;

    public $installation_marker = "jlint";
    
    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\jlint\\antic.exe",
                               'Linux'   => "%SA_HOME%resources/utility/jlint/antic");      

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

        $executable = $this->executable[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);
        
        $antic_results = array();

        if (getSystemOS() == "Windows") {
            $yasca->log_message("Forking external process (antiC)...", E_USER_WARNING);
            exec( "$executable  -java " . escapeshellarg($dir), $antic_results);
            $yasca->log_message("External process completed...", E_USER_WARNING);
        } else if (getSystemOS() == "Linux") {
            if ($this->USE_WINE) {
                $wine_arr = array();
                $wine_errorlevel = 0;
                exec("which wine", $wine_arr, $wine_errorlevel);
            
                if (preg_match("/no wine in/", implode(" ", $wine_arr)) || $wine_errorlevel == 1) {
                    $yasca->log_message("No Linux \"antiC\" executable and wine not found.", E_ALL);
                    return;
                } else {
                    $yasca->log_message("Forking external process (antiC)...", E_USER_WARNING);
                    exec( "wine $executable  -java " . escapeshellarg($dir), $antic_results);
                    $yasca->log_message("External process completed...", E_USER_WARNING);
                }
            } else {
                $yasca->log_message("Forking external process (antiC)...", E_USER_WARNING);
                exec( "$executable  -java " . escapeshellarg($dir), $antic_results);
                $yasca->log_message("External process completed...", E_USER_WARNING);
            }
        }
        
        if ($yasca->options['debug']) 
            $yasca->log_message("antiC returned: " . implode("\r\n", $antic_results), E_ALL);
        
        foreach ($antic_results as $antic_result) {
		    if (preg_match("/^(.*):(\\d+):(\\d+): (.*)$/", $antic_result, $matches)) {
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

<?php

/**
 * The IntegrityCheck Plugin uses IntegrityCheck to discover potential vulnerabilities in .class files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_IntegrityCheck extends Plugin {
    public $valid_file_types = array();

    public $is_multi_target = true;

    public $installation_marker = true;
    
    protected static $already_executed = false;
    
    private static $DEFAULT_IC_DATABASE = "./IntegrityCheck_Reference.ser";
    
	public function __construct($filename, $file_contents){
		if (static::$already_executed){
			$this->initialized = true;
			return;
		}
		parent::__construct($filename,$file_contents);
	}

    /**
     * Executes the cppcheck function. This calls out to the actual executable, but
     * process output comes back here.
     */
    public function execute() {
        if (static::$already_executed) return;  
        static::$already_executed = true;  
        
        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];

        $referenceFile = static::$DEFAULT_IC_DATABASE;
		if (isset($yasca->options['parameter']['IntegrityCheck_ReferenceFile'])) {
            $referenceFile = $yasca->options['parameter']['IntegrityCheck_ReferenceFile']; 
		}

		if (file_exists($referenceFile)) {
			$file_list = unserialize(file_get_contents($referenceFile));
		}

		if (isset($yasca->options['parameter']['IntegrityCheck_Initialize']) &&
            $yasca->options['parameter']['IntegrityCheck_Initialize'] == 'true') {
			foreach (dir_recursive($dir) as $file) {
				$file_list[$file] = sha1_file($file, false);
			}
			file_put_contents($referenceFile, serialize($file_list));
		}
		
		if (isset($file_list)) {
			foreach (dir_recursive($dir) as $file) {
				if ($file_list[$file] !== sha1_file($file, false)) {
					$message = "File contents were changed.";

 	                $result = new Result();
    	            $result->line_number = 0;
        	        $result->filename = $file;
            	    $result->plugin_name = $yasca->get_adjusted_alternate_name("IntegrityCheck", $message, $message);
                	$result->severity = $yasca->get_adjusted_severity("IntegrityCheck", $message, 5);
	                $result->category = "IntegrityCheck Finding";
    	            $result->category_link = "";
        	        $result->source = $yasca->get_adjusted_description("IntegrityCheck", $message, $message);
            	    $result->is_source_code = false;
                	$result->source_context = "";
	                array_push($this->result_list, $result);
				}
			}
		} else {
			$yasca->log_message("IntegrityCheck not initialized. Run with \"-d \"IntegrityCheck_Initialize=true\"\" to create baseline.", E_USER_WARNING);
		}
    }
}
?>

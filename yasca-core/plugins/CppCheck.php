<?php
require_once("lib/common.php");
require_once("lib/Plugin.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
/**
 * The cppcheck Plugin uses cppcheck to discover potential vulnerabilities in C/C++ files.
 *
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_CppCheck extends Plugin {
    public $valid_file_types = array();

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\cppcheck\\cppcheck.exe",
                               'Linux'   => "%SA_HOME%resources/utility/cppcheck/cppcheck" );

    public $installation_marker = "cppcheck";

    public $is_multi_target = true;
    
    protected static $already_executed = false;
    
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
        $cpp_results = array();

        $executable = $this->executable[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);
            
        $yasca->log_message("Forking external process (cppcheck)...", E_USER_WARNING);
        exec( $executable . " -q --unused-functions --xml " . escapeshellarg($dir) . " 2>&1", $cpp_results);
        $yasca->log_message("External process completed...", E_USER_WARNING);
            
        if ($yasca->options['debug']) 
            $yasca->log_message("Cppcheck returned: " . implode("\r\n", $cpp_results), E_ALL);
    
        $cpp_result = implode("\r\n", $cpp_results);
        if (preg_match("/No C or C\+\+ source files found\./", $cpp_result)) {
            $yasca->log_message("No C/C++ files found for Cppcheck to scan. Returning.", E_ALL);
            return;
        }
            
        $dom = new DOMDocument();
        if (!@$dom->loadXML($cpp_result)) {
            $yasca->log_message("Cppcheck did not return valid XML", E_USER_WARNING);
            return;
        }

        foreach ($dom->getElementsByTagName("error") as $error_node) {
            $result = new Result();
            $result->line_number = $error_node->getAttribute("line");
            $result->category = "Cppcheck: " . $error_node->getAttribute("id");
            $result->category_link = "http://sourceforge.net/projects/cppcheck/";
            $result->is_source_code = false;
            $message = $error_node->getAttribute("msg");
            $filename = $error_node->getAttribute("file");
            if ($filename == "") $filename = "(Unknown)";
            $source = $message;
            
            //CppCheck will output results differently if this is true. Compensate.
            if ($result->category == "cppcheck: id"){
            	$result->category = "cppcheck: General";
            	$filename = ltrim(strstr($message, "]:", true), "[");
            	$message = ltrim(strstr($message, "]:", false), "]: ");
            }
            
			$result->filename = $filename;
            $result->source = $message;
            $result->plugin_name = $yasca->get_adjusted_alternate_name("CppCheck", $message, "cppcheck");
            $result->severity = $yasca->get_adjusted_severity("CppCheck", $message, $error_node->getAttribute("severity"));
            $result->description = $yasca->get_adjusted_description("CppCheck", $message, "
<p>
        This finding was discoverd by cppcheck and is titled:<br/>
        <div style=\"margin-left:10px;\"><strong>$message</strong></div>
</p>
<p>
        <h4>References</h4>
        <ul>
                <li><a href=\"http://sourceforge.net/projects/cppcheck/\">cppcheck Home Page</a></li>
        </ul>
</p>");


            if (file_exists($filename) && is_readable($filename)) {
                $t_file = @file($filename, FILE_TEXT+FILE_IGNORE_NEW_LINES);
                //@todo Use mb encoding module to ensure it's read on properly OR upgrade to PHP 6.
                if ($t_file != false && is_array($t_file)) {
                    $result->source_context = array_slice( $t_file, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                }
            } else {
                $result->source_context = "";
            }

            array_push($this->result_list, $result);
        }
    }
}
?>

<?php
/**
 * The PMD Plugin uses PMD to discover potential vulnerabilities in .java files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_CppCheck extends Plugin {
    public $valid_file_types = array();

    private $executable = array('Windows' => "resources\\utility\\cppcheck.exe" );

    /**
     * This class is multi-target.
     */
    public $is_multi_target = true;

    /**
     * Executes the PMD function. This calls out to pmd.bat which then calls Java, but
     * process output comes back here.
     */
    function execute() {
        static $alreadyExecuted;
        if ($alreadyExecuted == 1) return;
        $alreadyExecuted = 1;

        if (getSystemOS() !== "Windows") return;        // only supporting Windows right now

        $yasca =& Yasca::getInstance();
        
        $dir = $yasca->options['dir'];
        $cpp_results = array();
            
        $yasca->log_message("Forking external process (cppcheck)...", E_USER_WARNING);
        exec( $this->executable[getSystemOS()] . " -q --xml " . escapeshellarg($dir) . " 2>&1", $cpp_results);
        $yasca->log_message("External process completed...", E_USER_WARNING);
            
        if ($yasca->options['debug']) 
            $yasca->log_message("PMD returned: " . implode("\r\n", $cpp_results), E_ALL);
    
            $cpp_result = implode("\r\n", $cpp_results);
            
        $dom = new DOMDocument();
        if (!@$dom->loadXML($cpp_result)) {
            $yasca->log_message("cppcheck did not return valid XML", E_USER_WARNING);
            return;
        }

        foreach ($dom->getElementsByTagName("error") as $error_node) {
            $filename = $error_node->getAttribute("file");
            $line_number = $error_node->getAttribute("line");
            $category = $error_node->getAttribute("id");
            $severity = $error_node->getAttribute("severity");
            $message = $error_node->getAttribute("msg");
                    $description = <<<END
<p>
        This finding was discoverd by cppcheck and is titled:<br/>
        <div style="margin-left:10px;"><strong>$message</strong></div>
</p>
<p>
        <h4>References</h4>
        <ul>
                <li><a href="http://sourceforge.net/projects/cppcheck/">cppcheck Home Page</a></li>
        </ul>
</p>
END;
            $result = new Result();
            $result->line_number = $line_number;
            $result->filename = $filename;
            $result->category = "cppcheck: $category";
            $result->category_link = "http://sourceforge.net/projects/cppcheck/";
            $result->is_source_code = false;
            $result->plugin_name = $yasca->get_adjusted_alternate_name("CppCheck", $message, "cppcheck");
            $result->severity = $yasca->get_adjusted_severity("CppCheck", $message, $severity);
                
            $result->source = $message;
            $result->description = $yasca->get_adjusted_description("CppCheck", $message, $description);

            if (file_exists($filename) && is_readable($filename)) {
                $t_file = @file($filename);
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

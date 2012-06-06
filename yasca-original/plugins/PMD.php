<?php
/**
 * The PMD Plugin uses PMD to discover potential vulnerabilities in .java files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_PMD extends Plugin {
    public $valid_file_types = array("java", "jsp");

    private $executable = array('Windows' => "resources\\utility\\pmd\\pmd.bat",
                                'Linux'   => "./resources/utility/pmd/pmd");
    /**
     * This class is multi-target.
     */
    public $is_multi_target = true;

    public function Plugin_PMD($filename, &$file_contents) {
        parent::Plugin($filename, $file_contents);
        if (!class_exists("DOMDocument")) {
            Yasca::log_message("DOMDocument is not available. PMD results are not available. Please install php-xml.", E_USER_WARNING);
            $this->canExecute = false;
        }
    }

    /**
     * Gets the specific rulesets to be included. The rule is that any plugin that has
     * an .xml extension is fair game, except for those starting with an underscore (_).
     */
    function get_rulesets() {
        $yasca =& Yasca::getInstance();
        $rulepaths = $yasca->plugin_file_list;
        $rpatharray = array();
        foreach ($rulepaths as $rulepath) {
            $pinfo = pathinfo($rulepath);
            if (isset($pinfo['extension']) &&
                $pinfo['extension'] == 'xml' && 
                !startsWith(basename($rulepath), "_")) {
                array_push($rpatharray, "" . $rulepath);
            }
        }
        return $rpatharray;
    }   

    /**
     * Executes the PMD function. This calls out to pmd.bat which then calls Java, but
     * process output comes back here.
     */
    function execute() {
        if (!$this->canExecute) return;

        static $alreadyExecuted;
        if ($alreadyExecuted == 1) return;
        $alreadyExecuted = 1;

        $yasca =& Yasca::getInstance();
        
        if (!$this->check_for_java()) {
            $yasca->log_message("The PMD Plugin requires JRE 1.4 or later.", E_USER_WARNING);
            return;
        }
        
        $dir = $yasca->options['dir'];
        foreach ($this->get_rulesets() as $ruleset) {
            $pmd_results = array();
            
            $yasca->log_message("Forking external process (PMD) for $ruleset...", E_USER_WARNING);
            exec( $this->executable[getSystemOS()] . " " . escapeshellarg($dir) . " " . escapeshellarg($ruleset), $pmd_results);
            $yasca->log_message("External process completed...", E_USER_WARNING);
            
            if ($yasca->options['debug']) 
                $yasca->log_message("PMD returned: " . implode("\r\n", $pmd_results), E_ALL);
        
            foreach ($pmd_results as $pmd_result) {
                if (stristr($pmd_result, "1) A java source code filename or directory")) {
                    $yasca->log_message("PMD was unable to start. Full output: " . implode("\r\n", $pmd_results), E_USER_WARNING);
                    break 2;
                }
            }
            $pmd_result = implode("\r\n", $pmd_results);
            
            $dom = new DOMDocument();
            if (
                !@$dom->loadXML($pmd_result)) {
                 $yasca->log_message("PMD did not return valid XML via ruleset [$ruleset]", E_USER_WARNING);
                 continue;
            }
            foreach ($dom->getElementsByTagName("file") as $file_node) {
                $filename = $file_node->getAttribute("name");
                
                foreach ($file_node->getElementsByTagName("violation") as $violation_node) {
                    $rule = $violation_node->getAttribute("rule");
                    $beginline = $violation_node->getAttribute("beginline");
                    $ruleset = $violation_node->getAttribute("ruleset");
                    $package = $violation_node->getAttribute("package");
                    $className = $violation_node->getAttribute("class");
                    $method = $violation_node->getAttribute("method");
                    $externalInfoUrl = $violation_node->getAttribute("externalInfoUrl");
                    $priority = $violation_node->getAttribute("priority");
                    
                    $example = "";
                    foreach ($violation_node->getElementsByTagName("examples") as $examples_node) {
                        foreach ($examples_node->getElementsByTagName("example") as $example_node) {
                            $example .= trim($example_node->nodeValue) . "<br/><br/>";
                        }
                    }
                    foreach ($violation_node->getElementsByTagName("description") as $description_node) {
                        $description = trim($description_node->nodeValue);
                    } 
                    
                    foreach ($violation_node->getElementsByTagName("message") as $message_node) {
                        $message = trim($message_node->nodeValue);
                    }                   
                    
                    // Do not include non-parsed files.
                    if (startsWith(trim($message), "Error while processing") ||
                        startsWith(trim($message), "Error while parsing")) {
                        $yasca->log_message("PMD was unable to parse file [$filename]", E_USER_WARNING);
                        continue;
                    }
                    
                    $result = new Result();
                    $result->line_number = $beginline;
                    $result->filename = $filename;
                    $result->category = "PMD: $rule";
                    $result->category_link = $externalInfoUrl;
                    $result->is_source_code = false;
                    $result->plugin_name = $yasca->get_adjusted_alternate_name("PMD", $rule, $rule);
                    $result->severity = $yasca->get_adjusted_severity("PMD", $rule, $priority);
                    
                    $result->source = $message;
                    $result->description = $yasca->get_adjusted_description("PMD", $rule, "<p>$description</p><h4>Example:</h4><pre class=\"fixedwidth\">$example</pre>");

                    $result->source_context = array_slice( file($filename), max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                    array_push($this->result_list, $result);
                }
            }
        }
    }
}
?>

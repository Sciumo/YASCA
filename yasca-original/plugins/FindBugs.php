<?php

/**
 * The FindBugs Plugin uses the open source tool FindBugs to discover potential 
 * vulnerabilities in compiled Java code.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_FindBugs extends Plugin {
    public $valid_file_types = array(); // All singletons do not use valid file types

    private $executable = array('Windows' => "resources\\utility\\findbugs\\findbugs_start.bat",
                                'Linux'   => "./resources/utility/findbugs/findbugs_start");
    
    /**
     * This class is multi-target.
     */
    public $is_multi_target = true;

    public function Plugin_FindBugs($filename, &$file_contents) {
        parent::Plugin($filename, $file_contents);
        if (!class_exists("DOMDocument")) {
            Yasca::log_message("DOMDocument is not available. FindBugs results are not available. Please install php-xml.", E_USER_WARNING);
            $this->canExecute = false;
        }
    }

    /**
     * Executes the scanning function. This calls out to findbugs.bat which then calls Java, but
     * process output comes back here.
     */
    function execute() {
        if (!$this->canExecute) return;

        static $alreadyExecuted;
        if ($alreadyExecuted == 1) return;
        $alreadyExecuted = 1;

        $yasca =& Yasca::getInstance();
        
        if (!$this->check_for_java(1.5)) {
            $yasca->log_message("The FindBugs Plugin requires JRE 1.5 or later.", E_USER_WARNING);
            return;
        }
        
        $dir = $yasca->options['dir'];

        $target_list = $yasca->target_list;
        foreach ($target_list as $target) {
            if (!endsWith($target, ".class") && !endsWith($target, ".jar")) {
                unset($target_list[array_search($target, $target_list)]);
            }
        }   
        if (count($target_list) == 0) {
            if ($yasca->options['debug']) {
                $yasca->log_message("FindBugs target list was empty. Nothing to do.", E_ALL);
            }
            return;
        }
        $descriptor_spec = array(
          0 => array("pipe", "r"),
          1 => array("pipe", "w"),
          2 => array("pipe", "w"));
        
        $pipes = array();
        
        $yasca->log_message("Forking external process (FindBugs)...", E_USER_WARNING);
        
        $process = proc_open($this->executable[getSystemOS()], $descriptor_spec, $pipes);
        $xml = "";
        
        if (is_resource($process)) {
            
            foreach ($target_list as $target) {         // Write each of the targets to stdin
                fwrite($pipes[0], $target . "\n");
                fflush($pipes[0]);
            }
            fclose($pipes[0]);

            // Standard Output
            while(!feof($pipes[1])) {
                $xml .= fread($pipes[1], 2048);         // 2k buffer, keep things moving
            }
            fclose($pipes[1]);

            // Check for errors
            $stderr = "";
            while(!feof($pipes[2])) {
                $stderr .= fread($pipes[2], 2048);      // 2k buffer, keep things moving
            }
            fclose($pipes[2]);
            if (strlen($stderr) > 0) {
                $yasca->log_message("FindBugs error: $stderr", E_USER_WARNING);
            }
        
            $returnValue = proc_close($process);
            if ($returnValue != 0) {
                $yasca->log_message("FindBugs did not complete sucessfully, error code: $returnValue", E_USER_WARNING);
                return;
            } else {
                $yasca->log_message("External process completed...", E_USER_WARNING);
            }
        } else {
            $yasca->log_message("Unable to execute FindBugs process.", E_USER_WARNING);
            return;
        }
    
        if ($yasca->options['debug']) 
            $yasca->log_message("FindBugs returned: " . $xml, E_ALL);

        $dom = @new DOMDocument();
        if (!$dom->loadXML($xml)) {
            $yasca->log_message("FindBugs did not return valid XML. Unable to parse.", E_USER_WARNING);
            return;
        }

        $bugPatternList = array();
        
        foreach ($dom->getElementsByTagName("BugPattern") as $bugPattern) {
            $type = $bugPattern->getAttribute("type");
            $details = $bugPattern->getElementsByTagName("Details");
            $details = $details->item(0)->nodeValue;
            $bugPatternList[$type] = $details;
        }
        
        foreach ($dom->getElementsByTagName("BugInstance") as $bugInstance) {
            $category = $bugInstance->getAttribute("category");
            $severity = $bugInstance->getAttribute("priority");
            $type = $bugInstance->getAttribute("type");
            $short_message = $bugInstance->getElementsByTagName("ShortMessage");
            $short_message = $short_message->item(0)->nodeValue;

            $long_message = $bugInstance->getElementsByTagName("LongMessage");
            $long_message = $long_message->item(0)->nodeValue;
            
            $source_line = $bugInstance->getElementsByTagName("SourceLine");

            $line_number = $source_line->item(0)->getAttribute("start");
            $rel_filename = $source_line->item(0)->getAttribute("sourcepath");
            $filename = $yasca->find_target_by_relative_name($rel_filename);
            if ($filename === false) {
                $filename = $yasca->options['dir'] . '/' . $rel_filename;
            }
            
            $description = $bugPatternList[$type];
            
            $result = new Result();
            $result->line_number = $line_number;
            $result->filename = $filename;
            $result->plugin_name = $yasca->get_adjusted_alternate_name("FindBugs", $short_message, $short_message);
            $result->severity = $yasca->get_adjusted_severity("FindBugs", $short_message, $severity);
            $result->category = "FindBugs: " . ucwords(strtolower(str_replace("_", " ",$category)));
            $result->category_link = "http://findbugs.sourceforge.net/bugDescriptions.html#$type";
            $result->is_source_code = false;
            $result->source = $short_message;
            $result->description = $yasca->get_adjusted_description("FindBugs", $short_message, "<b>$long_message</b><br/>$description");

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
        unset($dom);
        unset($xml);
    }
}
?>

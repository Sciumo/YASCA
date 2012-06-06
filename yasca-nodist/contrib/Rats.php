<?php

/**  
 * The Rats Plugin uses rats to discover potential vulnerabilities in or C/C++ files.  
 * This class is a Singleton that runs only once, returning all of the results that  
 * first time.  
 *
 * Thank you to Laurent for contributing this plugin!
 * Updated by Michael Maass, 06/25/2009 -- fixed bug where executable markers (%SA_HOME%) weren't properly replaced.
 *
 * @extends Plugin  
 * @package Yasca  
 */  
class Plugin_Rats extends Plugin {  
    public $valid_file_types = array("C", "PERL", "PYTHON");  

    public $is_multi_target = true;

    public $installation_marker = true; 

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\rats\\rats.exe",
                               'Linux' => "wine %SA_HOME%/resources/utility/rats/rats.exe");  
     
   /**  
    * Executes the Rats executable. This calls out to rats.exe, with output being processed.
    */  
    function execute() {  
        static $alreadyExecuted;  
        if ($alreadyExecuted == 1) return;  
        $alreadyExecuted = 1;  
     
        $yasca =& Yasca::getInstance();  
        $dir = $yasca->options['dir'];  
        $raw_results = array();  

	$executable = $this->executable[getSystemOS()];
	$executable = $this->replaceExecutableStrings($executable);

        if (getSystemOS() == "Windows" ||
            (getSystemOS() == "Linux" && !preg_match("/no wine in/", `which wine`))) {
            $yasca->log_message("Forking external process (RATS)...", E_USER_WARNING);
            exec( $executable . " --quiet --xml " . escapeshellarg($dir) . " 2>&1", $raw_results);
        } 
     
        if ($yasca->options['debug']) {  
            $yasca->log_message("RATS returned: " . implode("\r\n", $raw_results), E_ALL);  
        }  
        $raw_result = implode("\r\n", $raw_results);  
 
        $dom = new DOMDocument();  
        if (!$dom->loadXML($raw_result)) {  
            $yasca->log_message("RATS did not return a valid XML document. Ignoring.", E_USER_WARNING);  
            return;  
        }  
     
	$yasca->log_message("External process completed...", E_USER_WARNING);
        foreach ($dom->getElementsByTagName("vulnerability") as $error_node) {  
            $severity = $error_node->getElementsByTagName("severity")->item(0)->nodeValue;  
            $category = $error_node->getElementsByTagName("type")->item(0)->nodeValue;  
            $message = $error_node->getElementsByTagName("message")->item(0)->nodeValue;  

            foreach ($error_node->getElementsByTagName("file") as $file_node) {  
                $filename = $file_node->getElementsByTagName("name")->item(0)->nodeValue;  
                foreach ($file_node->getElementsByTagName("line") as $line_node) {  
                    $line_number = $line_node->nodeValue;  
                    $description = <<<END
<p>
        This finding was discoverd by RATS and is titled:<br/>
        <div style="margin-left:10px;"><strong>$message</strong></div>
</p>
<p>
        <h4>References</h4>
        <ul>
                <li><a href="http://www.fortify.com/security-resources/rats.jsp">RATS Home Page</a></li>
        </ul>
</p>
END;
                    $result = new Result();  
                    $result->line_number = $line_number;  
                    $result->filename = $filename;  
                    $result->category = "RATS: $category";  
                    $result->category_link = "http://www.fortify.com/security-resources/rats.jsp";  
                    $result->is_source_code = false;  
                    $result->plugin_name = $yasca->get_adjusted_alternate_name("RATS", $message, "rats");  
                    $result->severity = $yasca->get_adjusted_severity("RATS", $message, $severity);
 
                    $result->source = $message;  
                    $result->description = $yasca->get_adjusted_description("RATS", $message, $description);  
 
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
    }  
}  
?> 
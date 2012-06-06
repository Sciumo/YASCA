<?php

/**
 * This class looks for Java source code that might indicate an online log viewer.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_logging_logviewer extends Plugin {
    public $valid_file_types = array("java", "jsp");
    
    function execute() {
        $filename = basename($this->filename);
        if (strstr($filename, "Log")) {
            for ($i=0; $i<count($this->file_contents); $i++) {
                if (preg_match('/new File\(/', $this->file_contents[$i])) {
                    $result = new Result();
                    $result->line_number = $i+1;
                    $result->severity = 5;
                    $result->category = "Arbitrary File Disclosure (potential)";
                    $result->description = <<<END
            <p>
                This finding indicates what appears to be a servlet for accessing log files.
                Though this isn't actually a security finding, programmers often overlook
                protecting their log files appropriately. Ensure that access to the log
                files through this servlet occurs only to authorized individuals.
                
                It should be noted that the <b>java.io.File</b> object performs path
                canonicalization. This means that if you execute <span><b>new File("foo")</b></span>,
                it will look for <b>foo</b> in the current directory. If you execute
                <span><b>new File("bar/baz/../../foo")</b></span>, it will also look for
                <b>foo</b> in the current directory (assuming ./bar and ./bar/baz exist too).
                
                The typical attack against this type of vulnerbaility is to have the application
                disclose a sensitive file, such as <b>../../../../etc/passwd</b>. 
            </p>
            <p>
                <h4>References</h4>
                <ul>
                    <li>TODO</li>   
                </ul>
            </p>
END;
                    array_push($this->result_list, $result);
                }
            }
        }
    }
}
?>

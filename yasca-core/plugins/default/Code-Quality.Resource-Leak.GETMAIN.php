<?php

/**
 * This class looks for GETMAIN/FREEMAIN resource leaks in COBOL source code.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_code_quality_resource_leak_getmain extends Plugin {
    public $valid_file_types = array("sou", "src", "cob", "cbl");
    
    function execute() {
        $last_line_no = 0;
        $n=0;
        for ($i=0; $i<count($this->file_contents); $i++) {
            if (stristr($this->file_contents[$i], "GETMAIN")) {
                ++$n;
                $last_line_no = $i;
            }
            if (stristr($this->file_contents[$i], "FREEMAIN")) 
                --$n;
        }
        if ($n != 0) {      
            $result = new Result();
            $result->line_number = $last_line_no + 1;
            $result->severity = 2;
            $result->category = "Unreleased Resource";
            $result->category_link = "http://www.owasp.org/index.php/Unreleased_Resource";
            array_push($this->result_list, $result);                
        }
    }
}
?>
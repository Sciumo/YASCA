<?php

/**
 * This class looks for XSS vulnerabilities of the form:
 *   String foo = request.getParameter("foo");
 *   ...
 *   out.println(foo);
 * @extends Plugin
 * @package Yasca
 */
class Plugin_injection_xss_println extends Plugin {
    public $valid_file_types = array("jsp", "java");
    
    function execute() {
        for ($i=0; $i<count($this->file_contents); $i++) {
            if (preg_match('/([a-zA-Z0-9\_]+)\s*\=\s*req\.getParameter\(/', $this->file_contents[$i], $matches)) {
                $field_name = $matches[1];
                for ($j=$i+1; $j<count($this->file_contents); $j++) {
                    if (!preg_match('/out\.println/', $this->file_contents[$j]) ||
                        !preg_match("/$field_name/", $this->file_contents[$j])) {
                        continue;
                    }       
                    $result = new Result();
                    $result->line_number = $j+1;
                    $result->severity = 1;
                    $result->category = "Cross-Site Scripting";
                    $result->category_link = "http://www.owasp.org/index.php/Cross_Site_Scripting";
                    array_push($this->result_list, $result);                
                }
             }
        }
    }
}
?>
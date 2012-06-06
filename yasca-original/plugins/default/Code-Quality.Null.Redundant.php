<?php

/**
 * This class looks for cases in code like this:
 *      foo.bar = f();
 *      if (foo != null) {   <-- this is redundant, since foo couldn't have been null
 *        ...                    on the previous line!
 * FP Condition:
    if (x) {
        return (y.foo());
    if (y != null && ...)

 * @extends Plugin
 * @package Yasca
 */
class Plugin_code_quality_null_redundant extends Plugin {
    public $valid_file_types = array("jsp", "java");
    
    function execute() {
        for ($i=0; $i<count($this->file_contents)-1; $i++) {
            $line = $this->file_contents[$i];
            $nextline = $this->file_contents[$i+1];
            $matches = array();         
            if (preg_match('/([a-zA-Z0-9_]+)\./', $line, $matches) &&
                (!preg_match('/' . $matches[1] . '\s*=/', $line)) &&
                preg_match("/" . $matches[1] . "\s*\!\=\s*null/", $nextline)) {
                $result = new Result();
                $result->line_number = $i+1;
                $result->severity = 4;
                $result->category = "Possible Redundant Null Check";
                array_push($this->result_list, $result);
             }
             $nextline = null;
             $matches = null;
             $line = null;
        }
    }
}
?>

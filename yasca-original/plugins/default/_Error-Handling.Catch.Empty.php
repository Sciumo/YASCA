<?php

/**
 * This class looks for empty catch blocks:
 *      try {
 *         ...
 *      } catch(Exception e) {
 *      }                    <-- ignores exception
 *      foo.bar = f();
 *      if (foo != null) {   <-- this is redundant, since foo couldn't have been null
 *        ...                    on the previous line!
 * @extends Plugin
 * @package Yasca
 * @deprecated Use PMD:EmptyCatchBlock instead
 */
class Plugin_error_handling_catch_empty extends Plugin {
    public $valid_file_types = array("jsp", "java");
    
    function execute() {
        for ($i=0; $i<count($this->file_contents)-1; $i++) {
            $line = $this->file_contents[$i];
            $nextline = $this->file_contents[$i+1];
            if ( (stristr($line, "catch(") !== FALSE &&
                  $nextline == "}") ||
                 (stristr($line, "catch(") !== FALSE &&
                  stristr($line, "Exception") !== FALSE &&
                  stristr($line, "{}") != FALSE) ) {
                $result = new Result();
                $result->line_number = $i+1;
                $result->severity = 4;
                $result->category = "Empty Catch Block";
                $result->category_link = "http://www.owasp.org/index.php/Empty_Catch_Block";
                array_push($this->result_list, $result);
             }
        }
    }
}
?>

<?php

/**
 * This class looks for code quality issues like:
 *   char foo[10];
 *   
 *   return foo;
 * @extends Plugin
 * @package Yasca
 */
class Plugin_codequality_function_returns_pointer_to_stack extends Plugin {
    public $valid_file_types = array("c", "cpp");
    
    private $buffer = 25;       // Number of lines to look back from "return X"
    
    function execute() {
        for ($i=0; $i<count($this->file_contents); $i++) {
            if (preg_match('/return ([a-zA-Z\_][a-zA-Z0-9_]*)\s*;/', $this->file_contents[$i], $matches)) {
                $variable_name = $matches[1];
                for ($j=$i-1; $j>max(0, $i-$this->buffer); $j--) {
                    if (!preg_match("/[a-zA-Z\_][a-zA-Z0-9_]*\s+$variable_name\s*\[/", $this->file_contents[$j]))
                    continue;
                    $result = new Result();
                    $result->plugin_name = "Function Returns Pointer to Stack"; 
                    $result->line_number = $i+1;
                    $result->severity = 1;
                    $result->category = "Code Quality: Functions";
                    $result->category_link = "#TODO";
                    $result->description = <<<END
            <p>
                TBD
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
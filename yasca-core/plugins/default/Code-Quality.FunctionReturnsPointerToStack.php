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
    
    protected $buffer = 25;       // Number of lines to look back from "return X"
    
    function execute() {
    	$count = count($this->file_contents);
        for ($i=0; $i<$count; $i++) {
            if (preg_match('/^(?!\/\/)(?:.(?!\/\/))*?\breturn ([a-zA-Z\_][a-zA-Z0-9_]*)\s*;/', $this->file_contents[$i], $matches)) {
                $variable_name = $matches[1];
                $inner_count = max(0, $i-$this->buffer);
                for ($j=$i-1; $j>$inner_count; $j--) {
                    if (!preg_match("/^(?!\/\/)(?:.(?!\/\/))*?\b[a-zA-Z\_][a-zA-Z0-9_]*\s+$variable_name\s*\[/", $this->file_contents[$j]))
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
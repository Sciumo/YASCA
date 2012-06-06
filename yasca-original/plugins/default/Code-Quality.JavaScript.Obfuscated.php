<?php

/**
 * This class looks for obfuscated JavaScript.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_code_quality_javascript_obfuscated extends Plugin {
    public $valid_file_types = array("js");
    
    
    /**
     * Executes the plugin to scan a particular file. Uses a cache to speed things up.
     */
    function execute() {
        $numSpaces = 0;
        $totalSize = 0;
        for ($i=0; $i<count($this->file_contents); $i++) {
            $numSpaces += substr_count($this->file_contents[$i], " ");
            $totalSize += strlen($this->file_contents[$i]) + 1;
        }
        if ($totalSize > 0 &&
            (double)$numSpaces / (double)$totalSize < 0.025) {
            $result = new Result();
            $result->plugin_name = "Obfuscated JavaScript Code"; 
            $result->severity = 3;
            $result->source = "This file appears to contain obfuscated or encrypted code.";
            $result->is_source_code = false;
            $result->category = "Code Quality: Obfuscation";
            $result->description = <<<END
                <p>
                    Obfuscated JavaScript code, unless taken from a trusted source, can be dangerous
                    in an environment, resulting in the unintended execution of code. 
                </p>
                <p>
                    <h4>References</h4>
                    <ul>
                        <li><a href="http://en.wikipedia.org/wiki/Obfuscated_code#Obfuscation_in_malicious_software">Obfuscation in Malicious Software</a></li>
                        <li><a href="http://handlers.sans.org/dwesemann/decode/index.html">Decoding JavaScript</a></li>
                        
                    </ul>
                </p>
END;
            array_push($this->result_list, $result);                
        }

    }
}
?>
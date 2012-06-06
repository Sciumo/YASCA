<?php

/**
 * This class looks for cases in code like this:
 * 		free(x);
 *      func(x);
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_C_UseAfterFree extends Plugin {
    public $valid_file_types = array("C");
    
    function execute() {
        for ($i=0; $i<count($this->file_contents)-1; $i++) {
            $line = trim($this->file_contents[$i]);
			$possible_free = 0;
			if (preg_match('/^\s*free\(\s*(.*)\s*\)/i', $line, $matches)) {
				$variable = trim($matches[1]);
				for ($j=$i+1; $j<min($i+10, count($this->file_contents)-1); $j++) {
					$line2 = trim($this->file_contents[$j]);
					if (preg_match('/{/', $line2)) $possible_free++;
					if (preg_match('/}/', $line2)) $possible_free--;
					if ($possible_free < 0) $possible_free = 10000;
					if (preg_match('/\(\s*.*[\s,]?' . $variable . '[\s,\)]/', $line2)) {
     		           	$result = new Result();
						$result->plugin_name = "Use After Free";
 		               	$result->line_number = $j+1;
        		       	$result->severity = $possible_free != 0 ? 4 : 3;
        	        	$result->category = "Use After Free";
						$result->category_link = "http://www.owasp.org/index.php/Double_Free";
						$ii = $i+1;
						$jj = $j+1;
						$possibly = $possible_free != 0 ? "<strong>possibly</strong>" : "";
                    	$result->description = <<<END
<p>
	A use-after-free condition $possibly exists when memory is referenced after is is freed back to the memory management system using free().
	This could result in a program crash, unexpected values, or even arbitrary code execution.
</p>
<p>
	<strong>In this case, the use-after-free was detected on lines $ii and $jj of the source code.</strong>
<p>
    <h4>References</h4>
    <ul>
      <li><a href="http://cwe.mitre.org/data/definitions/416.html">http://cwe.mitre.org/data/definitions/416.html</a></li>
    </ul>
</p>
END;
	 	               	array_push($this->result_list, $result);
					}
				}
             }
             $matches = $line = $line2 = null;
        }
    }
}
?>

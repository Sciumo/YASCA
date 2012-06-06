<?php

/**
 * This class looks for cases in code like this:
 * 		free(x);
 *      ... [no close-braces in here]
 *      free(x);
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_C_DoubleFree extends Plugin {
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
					if (preg_match('/^\s*free\(\s*' . $variable . '\s*\)/', $line2)) {
     		           	$result = new Result();
						$result->plugin_name = "Double Free";
 		               	$result->line_number = $j+1;
        		       	$result->severity = $possible_free != 0 ? 4 : 3;
        	        	$result->category = "Double Free";
						$result->category_link = "http://www.owasp.org/index.php/Double_Free";
						$ii = $i+1;
						$jj = $j+1;
						$possibly = $possible_free != 0 ? "<strong>possibly</strong>" : "";
                    	$result->description = <<<END
<p>
	A double-free condition $possibly exists when dynamically allocated memory is freed twice (using the free() function). This can lead to a memory corruption
	and possibly a buffer overflow condition.
</p>
<p>
	<strong>In this case, the double free was detected on lines $ii and $jj of the source code.</strong>
<p>
    <h4>References</h4>
    <ul>
      <li><a href="http://www.owasp.org/index.php/Double_Free">http://www.owasp.org/index.php/Double_Free</a></li>
      <li><a href="http://cwe.mitre.org/data/definitions/415.html">http://cwe.mitre.org/data/definitions/415.html</a></li>
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

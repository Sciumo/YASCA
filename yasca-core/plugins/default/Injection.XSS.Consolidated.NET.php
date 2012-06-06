<?php

/**
 * This class looks for XSS vulnerabilities.
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_injection_xss_net extends Plugin {
    public $valid_file_types = array("aspx", "cs", "vb");
    
    function execute() {
		$SOURCE=0;
		$SINK=1;
		$xss_array=array(); //Array to store the sources/sinks of the XSS problems
		$line_numbers=array();
		
		$count = count($this->file_contents);

        for ($i=0; $i<$count; $i++) {
	    if (preg_match('/\s*([a-zA-Z0-9\_]+)\s*\=\s*Request(\.RawUrl|\.QueryString|\.Params)/i', 
		    $this->file_contents[$i], $matches)) {
                $variable_name = $matches[1];

                for ($j=$i+1; $j<$count; $j++) {
                    if (preg_match('/Response.Write\(.*(\+*|\&*)\s*' . $variable_name . '(\+*|\&*)\s*/', $this->file_contents[$j])) {
						if ( !isset($xss_array[$variable_name][$SOURCE]) )
						{
							$xss_array[$variable_name][$SOURCE]="";
							$xss_array[$variable_name][$SINK]="";
						}
						if (!stristr( $xss_array[$variable_name][$SINK], "".($j+1) ) )
						{
							$xss_array[$variable_name][$SINK]=$xss_array[$variable_name][$SINK]." ".($j+1);
						}
						if (!stristr( $xss_array[$variable_name][$SOURCE], "".($i+1) ) )
						{
							$xss_array[$variable_name][$SOURCE]=$xss_array[$variable_name][$SOURCE]." ".($i+1);
						}
						$line_numbers[$variable_name]=$j+1;
                        continue;
                    }
                }
            }
        }

		foreach( $xss_array as $key=>$value)
		{
			
			$result = new Result();
			$result->plugin_name = "Cross-Site Scripting via Request() in ASP"; 
			$result->line_number = $value[0];
			$result->severity = 1;
			$result->category = "XSS Problems (Source/Sink in Description)";
			$result->category_link = "http://www.owasp.org/index.php/Cross_Site_Scripting";
			$result->description = <<<END
			<p>
			Source lines are: $value[0]<br/>
			Sink lines are: $value[1]<p>
			Cross-Site Scripting (XSS) vulnerabilities can be exploited by an attacker to 
			impersonate or perform actions on behalf of legitimate users.
        
			The attacker could exploit this vulnerability by directing a victim to visit a URL
			with specially crafted JavaScript to perform actions on the site on behalf of the 
			attacker, or to simply steal the session cookie. <p>
			A solution to this problem would be to filter out bad characters such as '&lt;'  or '&gt;' at the source  
			or use HtmlEncoding at the sink.
			</p>
			<p>
				<h4>References</h4>
				<ul>
					<li><a href="http://www.owasp.org/index.php/XSS">http://www.owasp.org/index.php/XSS</a></li>
					<li><a href="http://www.ibm.com/developerworks/tivoli/library/s-csscript/">Cross-site Scripting article from IBM</a></li>
				</ul>
			</p>
END;
			array_push($this->result_list, $result);                
		}
    }
}
?>

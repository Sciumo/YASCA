<?php
require_once("lib/common.php");
require_once("lib/Plugin.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
/**
 * The Pixy Plugin uses Pixy to discover potential vulnerabilities in PHP 4 files.
 * Uses Pixy 3.0.3
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Pixy extends Plugin {
    public $valid_file_types = array("php", "php4");

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\pixy\\pixy.bat",
                               'Linux'   => "%SA_HOME%resources/utility/pixy/pixy");
    
    //@todo Pixy is the #2 slowest plugin because it causes a full instantiation of the java JVM for each and every file, rather than once.
    //Pixy is multi-target in that it will explore out to other nearby files, but it's not multi-target in the sense that you can send it against a directory.
    public $is_multi_target = false;
    
    /** Shortcuts the Java check because Pixy is not multi-target */
    protected static $minimum_java_exists = null;

    public $installation_marker = "pixy";
    
    
    /**
     * Executes the Pixy function. This calls out to pixy.bat which then calls Java, but
     * process output comes back here.
     */
    public function execute() {
    	// added because Pixy is a one-at-a-time plugin
        if (isset(static::$minimum_java_exists) && !static::$minimum_java_exists) return;
        
        $yasca =& Yasca::getInstance();
        
        if (!isset(static::$minimum_java_exists)){
        	static::$minimum_java_exists = $this->check_for_java(1.6);
	        if (!static::$minimum_java_exists) {
	            $yasca->log_message("The Pixy Plugin requires JRE 1.6 or later.", E_USER_WARNING);
	            return;
	        }
        }
        
        $executable = $this->executable[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);
        
        $pixy_results = array();
        exec( $executable . " " . escapeshellarg($this->filename) . " 2>&1", $pixy_results);
               
        if ($yasca->options['debug'])
            $yasca->log_message("Pixy returned: " . implode("\r\n", $pixy_results), E_ALL);
    
        
        $rule = "";
        $category_link = "#";
                
        foreach($pixy_results as $line) {
            if (preg_match('/^XSS Analysis BEGIN/i', $line)) { 
            	$rule = "Cross-Site Scripting"; 
            	$category_link = "http://www.owasp.org/index.php/Cross_Site_Scripting"; 
            	$description = <<<END
    <p>
		Cross-Site Scripting (XSS) vulnerabilities can be exploited by an attacker to 
		impersonate or perform actions on behalf of legitimate users.

		The attacker could exploit this vulnerability by directing a victim to visit a URL
		with specially crafted JavaScript to perform actions on the site on behalf of the 
		attacker, or to simply steal the session cookie. 
	</p>
	<p>
		<h4>References</h4>
		<ul>
			<li><a href="http://www.owasp.org/index.php/XSS">http://www.owasp.org/index.php/XSS</a></li>
			<li><a href="http://www.acunetix.com/cross-site-scripting/scanner.htm">Acunetix Web Vulnerability Scanner (<span style="color:red;font-weight:bold;">free</span>, but only does XSS scanning)</a></li>
			<li><a href="http://www.ibm.com/developerworks/tivoli/library/s-csscript/">Cross-site Scripting article from IBM</a></li>
		</ul>
	</p>
END;
            	continue;
            } elseif (preg_match('/^SQL Analysis BEGIN/i', $line)) {
            	$rule = "SQL Injection";
            	$category_link = "http://www.owasp.org/index.php/SQL_Injection";
            	$description = <<<END
<p>
	<h4>Possible SQL Injection</h4>
	SQL injection is a code injection technique that exploits a security vulnerability occurring in the database 	
	layer of an application. The vulnerability is present when user input is either incorrectly filtered for string literal
	escape characters embedded in SQL statements or user input is not strongly typed and thereby unexpectedly executed. It 
	is an instance of a more general class of vulnerabilities that can occur whenever one programming or scripting language
	is embedded inside another. SQL injection attacks are also known as SQL insertion attacks.
</p>	
<p>
	<h4>References</h4>
	<ul>
		<li><a href="http://en.wikipedia.org/wiki/SQL_injection">Wikipedia: SQL Injection</a></li>
	</ul>
</p>
END;
            	continue; 
            } elseif (preg_match('/^File Analysis BEGIN/i', $line)) {
            	$rule = "File-Related Vulnerability";
            	$category_link = "#";
				$description = "TODO"; //@todo Create pixy file analysis description
            	continue;
            } elseif($rule == ""){
            	continue;
            }
            

            //@todo Suspicion that this grep will not match "File-Related Vulnerabilities"
            if (preg_match('/^\-\d*(.*?):(\d+)$/', $line, $results)) {
                //if (!file_exists($vFilename)) continue;
                $vFilename = correct_slashes(trim($results[1]));
                $vLine = $results[2];
                $priority = 1;

                $result = new Result();
                $result->filename = $vFilename;
                $result->line_number = $vLine;
                $result->category = $rule;
                $result->category_link = $category_link;
                $result->is_source_code = true;
                $result->plugin_name = $yasca->get_adjusted_alternate_name("Pixy", $rule, $rule);
                $result->severity = $yasca->get_adjusted_severity("Pixy", $rule, $priority);


                $result->description = $yasca->get_adjusted_description("Pixy", $rule, $description);

                if ($vFilename == $this->filename){
                	$filecontents = $this->file_contents;
                }
                else{
	            	//@todo Use mb encoding module to ensure it's read on properly OR upgrade to PHP 6.
	                $filecontents = @file($vFilename, FILE_TEXT+FILE_IGNORE_NEW_LINES);
                }
				$result->source_context = array_slice($filecontents, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                $result->source = array_slice($filecontents, $result->line_number-1, 1 );
                $result->source = $result->source[0];
				$this->result_list[] = $result;
				
		    }
		}
    }
}
?>

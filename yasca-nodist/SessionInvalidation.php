<?php

/**
 * The SessionInvalidation Plugin uses PHP to identify if any HTTP Session objects are created by the application.
 * If any HTTP Session objects are created, the plugin then checks for Session.Invalidate calls.  The presence of 
 * HTTP Session objects and the absence of Session.Invalidate calls could indicate a session fixation vulnerability.
 *
 * Plugin by Josh Berry, 3/31/2009.
 *
 * @extends Plugin
 * @package Yasca
 */

class Plugin_SessionInvalidation extends Plugin {
	public $valid_file_types = array("java", "jsp");
	public $session_files = array();	

	function execute() {
		static $alreadyExecuted;
		if ($alreadyExecuted == 1) return;
		$alreadyExecuted = 1;

		$yasca =& Yasca::getInstance();
		$dir = $yasca->options['dir'];   

		$this->start_scan($this->dir_recursive($dir));
	}

	function dir_recursive($start_dir) {
		$file_types = array("\.jsp", "\.java");
	     	$files = array();
		$start_dir = str_replace("\\", "/", $start_dir);    // canonicalize
		
		if (is_dir($start_dir)) {
			$fh = opendir($start_dir);

			while (($file = readdir($fh)) !== false) {
				if (strcmp($file, '.')==0 || strcmp($file, '..')==0) continue;

				$filepath = $start_dir . '/' . $file;
				if ( is_dir($filepath) ) {
					$files = array_merge($files, $this->dir_recursive($filepath));
				} else {
					for ($i = 0; $i < sizeof($file_types); $i++) {
						if (eregi($file_types[$i], $file)) {
							array_push($files, $filepath);
						}
					}

				}
			}
			closedir($fh);
		} else {
			$files = false;
		}
		return $files;
	}

	function start_scan($file_values) {
		$session_invalidates = 0;
		$session_files = array();
		$f = 0;

		for ($i = 0; $i < sizeof($file_values); $i++) {
			$lines = file($file_values[$i], FILE_IGNORE_NEW_LINES);

			foreach ($lines as $line_num => $line) {
				$line_check = rtrim($line);

				if (preg_match("/(Session\s[a-zA-Z0-9_]|LoginContext)/i", $line_check)) {
					array_push($session_files, $file_values[$i] . ":" . ($line_num+1));
				}

				if (preg_match("/(Session\.Invalidate|HttpSession\.Invalidate|\.Invalidate\(\))/i", $line)) {
					$session_invalidates = 1;
				}
			}
		}
		
		if ($session_invalidates == 0) {
			for ($m = 0; $m < sizeof($session_files); $m++) {
				$file_results = split(":", $session_files[$m]);
				$full_file = $file_results[0] . ":" . $file_results[1];
				$file_line = $file_results[2];

				$result = new Result();
				$result->line_number = $file_line;
				$result->filename = $full_file;
				$result->plugin_name = "Session Fixation";
				$result->severity = 2;
				$result->category = "Session Fixation";
				$result->category_link = "http://www.owasp.org/index.php/Session_Fixation";
		            $result->is_source_code = false;
				$result->source = "Session objects created but no session invalidation found anywhere in code";
				$result->description = "Authenticating a user without invalidating any existing session identifier gives an attacker the opportunity to steal authenticated sessions";

            		if (file_exists($full_file) && is_readable($full_file)) {
                			$t_file = @file($full_file);
			
			            if ($t_file != false && is_array($t_file)) {
                  			$result->source_context = array_slice( $t_file, max( $result->line_number-((5+1)/2), 0), 5);
			            }
		            } else {
            			$result->source_context = "";
            		}
				array_push($this->result_list, $result);
			}
		}
	}
}

?>
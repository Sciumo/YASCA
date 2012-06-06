<?php
require_once("lib/common.php");
require_once("lib/Plugin.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
require_once("lib/PreProcessors.php");
/**
 * The Grep Plugin is a special plugin that facilitates .grep psuedo-plugins, which
 * are just files in the PLUGINS directory that contain necessary information to scan
 * the target files.
 * 
 *
 * The .grep files must be in an encoding transparent to ISO-8859-1 and have a specific format.
 *  name = <name of plugin> (0 or 1) (default: "Grep: <basename of .grep file>")
 *  file_type = <list,of,extensions> (exactly 1) (default: scan all extensions)
 *  pre_grep = <PCRE-style regular expression> (0 or 1) (default: none)
 *  loohahead_length = <int A grep result must be no more than this many lines of code away from a pre_grep result.> (default: 10) (0 or 1)
 *  grep = <PCRE-style regular expression> (1+) 
 *  category = <category name> (1)
 *  preprocess = <PHP function that takes one argument for the file contents (array of strings) and returns an array of strings.> (default: none) (0 or 1)
 *  fix = <PHP code that evaluates to a string> (default: none) (0 or 1)
 *  category_link = <link to more information about the issue> (default: none) (0 or 1)
 *  severity = <severity on 1-5 scale, 1=critical, 5=informational> (default: 5) (0 or 1)
 *  description = <description of the issue. Scans multiple lines until "END;" is found by itself on a line> (default: none) (0 or 1)
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_Grep extends Plugin {
	/**
	 * The union of all valid file types accepted by the grep sub-plugins
	 * @var array of strings
	 */
	protected static $union_of_valid_file_types;
	
	/**
	 * @var array of Grep_File()s
	 */
	protected static $grep_files;

	//Not used in the typical way. 
	//It is set after the plugin is instantiated and used in execute() to skip specific grep files.
	public $valid_file_types; //see constructor

	public function __construct($filename, $file_contents){
		if (!isset(static::$grep_files)){
			static::load_grep_files();
		}
		 
		// Capture all of the valid file types
		if (!isset(static::$union_of_valid_file_types)) {
			static::set_union_of_filetypes();
		}

		$this->valid_file_types = static::$union_of_valid_file_types;
		parent::__construct($filename, $file_contents);
	}
	
	
	protected static function load_grep_files(){
		static::$grep_files = array();
		$yasca =& Yasca::getInstance();
		foreach ($yasca->plugin_file_list as $plugin){
			if (startsWith($plugin, "_") || !endsWith($plugin, ".grep"))
				continue;
			
			$grep_content = file($plugin, FILE_TEXT+FILE_IGNORE_NEW_LINES);
			if (!array_any($grep_content, function($line) {return preg_match('/^\s*grep\s*=\s*(.*)/i', $line);}))
				continue;
				
			if (!array_any($grep_content, function($line) {return preg_match('/^\s*category\s*=\s*(.*)/i', $line);}))
				continue;

			//Code below this line means it's a keeper plugin.
			array_walk($grep_content, function (&$line) {
				$line = trim($line);
			});
			static::$grep_files[] = new Grep_File($plugin, $grep_content);
		}
	}
	
	protected static function set_union_of_filetypes(){
		static::$union_of_valid_file_types = array();
		$yasca =& Yasca::getInstance();
		$yasca->log_message("Loading all scannable file types for the Grep plugin.", E_ALL);
		
		foreach (static::$grep_files as $grep_file) {
			static::$union_of_valid_file_types = array_merge(static::$union_of_valid_file_types, 
				$grep_file->valid_file_types);
		}
		static::$union_of_valid_file_types = array_unique(static::$union_of_valid_file_types);	
	}


	public function execute() {
		$yasca =& Yasca::getInstance();
		
		$this->test_and_convert_file_contents();
		$filename = null;

		foreach (static::$grep_files as $grep_file) {
			//If the loaded grep doesn't apply to the current file, then move on to the next grep.
			if (!$this->check_in_filetype($this->filename, $grep_file->valid_file_types)){
				continue;
			}
			
			$yasca->log_message("Using Grep [$grep_file->name] to scan [$this->filename]", E_USER_NOTICE);

			$file_contents = $this->file_contents;

			if (isset($grep_file->preprocess) ) {
				if ($yasca->options["debug"])
				$yasca->log_message("Before pre-processing, file contents are: \n" . implode("\n", $file_contents), E_ALL);

				//@todo This call can cost an additional copy of the file contents in memory.
				$file_contents = @call_user_func($grep_file->preprocess, $file_contents);

				if ($yasca->options["debug"])
				$yasca->log_message("After pre-processing with {$grep_file->preprocess} for $grep_file->name, file contents are: \n" . implode("\n", $file_contents), E_ALL);
			}
			
			$pre_matches = array();         // holds line numbers of pre_grep matches
			$pre_match_count = 0;
			if (isset($grep_file->pre_grep)) {
				$pre_matches = @preg_grep($grep_file->pre_grep, $file_contents);
				$pre_match_count = count($pre_matches);
				if ($pre_match_count == 0) continue;
			}

			foreach ($grep_file->grep as $grep) {
				$matches = @preg_grep($grep, $file_contents);

				//If preg_grep did not return an array, then an error occurred.
				//Assume that the grep string is invalid.
				if (!is_array($matches)) {
					if (!isset($yasca->general_cache["grep.errored"]))
					$yasca->general_cache["grep.errored"] = array();

					//@todo This list of ignored greps should be used for more than just blocking warning messages.
					$c =& $yasca->general_cache["grep.errored"];

					if (!isset($c[$grep])) {
						$yasca->log_message("Invalid grep expression [$grep].", E_USER_WARNING);
						$c[$grep] = true;
					}
					continue;
				}

				foreach ($matches as $line_number => $match) {
					if ( $pre_match_count > 0 && 
						!any_within($pre_matches, $line_number+1, $this->lookahead_length)) {
						continue;
					}

					$result = new Result();
					$result->line_number = $line_number + 1;
					if (!isset($filename)) $filename = str_replace($yasca->options['dir'], "", correct_slashes($this->filename));
					$result->filename = $filename;
					$result->severity = $grep_file->severity;
					$result->category = $grep_file->category;
					$result->category_link = $grep_file->category_link;
					$result->description = $grep_file->description; 
					$result->source = $match;
					$result->source_context = array_slice( $file_contents, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
					$result->plugin_name = $grep_file->name;
					if (isset($this->fix) && $this->fix !== "")
						$result->proposed_fix = eval($this->fix);

					$yasca->log_message("Found a result on line {$result->line_number} ({$grep_file->name})", E_ALL);
					array_push($this->result_list, $result);
				}
			}
		}
	}
	
	protected function test_and_convert_file_contents(){
		  // Perform UTF Conversion, if necessary
          if (is_array($this->file_contents)) {
          	
          	//Before doing expensive computation, check the obvious first.
          	if (!isset($this->file_contents[0])) return;
          	$sample = $this->file_contents[0];
          	if (strlen($sample) <= 2) return;
			
		    $c0 = ord($sample[0]);
		    $c1 = ord($sample[1]);
		
		    if ($c0 == 0xFE && $c1 == 0xFF) {
		        //Is probably unicode 16
		    } else if ($c0 == 0xFF && $c1 == 0xFE) {
		        //Is probably unicode 16
		    } else {
		    	//Isn't unicode 16. Avoid expensive work.
		        return;
		    }
          	
          	//@todo This call costs three copies of the file contents in memory.
           	$this->file_contents = explode("\n", utf16_to_utf8(implode("\n", $this->file_contents)));
         } else {
         	//@todo This call costs an additional copy of the file contents in memory.
            $this->file_contents = utf16_to_utf8($this->file_contents);        
          }
	}
}

final class Grep_File{
	public $name;
	public $file_type;
	public $grep = array();
	public $pre_grep;                   /* Pre Lookahead Grep - must exist within $lookahead before a $grep */
	public $lookahead_length = 10;      /* Index of pre_grep to grep must be within $lookahead_length       */
	public $fix;
	public $category;
	public $category_link = "";
	public $severity = 5;
	public $tags;
	public $description = "";
	public $preprocess;
	public $valid_file_types;
	
	/**
	 * Creates a Grep_File from a series of lines from a grep text file.
	 * @param string $filename
	 * @param array $file_contents array of strings of the grep file to parse.
	 */
	public function Grep_File($filename, $file_contents){
		$this->internalize_grep_contents($file_contents);
		
		// Set the name if it isn't already specified
		if (!isset($this->name) || $this->name == "")
			$this->name = basename($filename, ".grep");
	}

	private function internalize_grep_contents($grep_content){
		$count = count($grep_content);
		for ($i=0; $i<$count; $i++) {
			$matches = array();
			if (!isset($this->name) &&
				preg_match('/^\s*name\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->name = $matches[1];
			elseif (!isset($this->valid_file_types) &&
					preg_match('/^\s*file_type\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->valid_file_types = explode(",", $matches[1]);
			elseif (preg_match('/^\s*grep\s*=\s*(.*)/i', $grep_content[$i], $matches)) array_push($this->grep, $matches[1]);
			elseif (!isset($this->pre_grep) &&
					preg_match('/^\s*pre_grep\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->pre_grep = $matches[1];
			elseif (!isset($this->category) &&
					preg_match('/^\s*category\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->category = $matches[1];
			elseif (!isset($this->preprocess) &&
					preg_match('/^\s*preprocess\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->preprocess = trim($matches[1]);
			elseif (preg_match('/^\s*lookahead_length\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->lookahead_length = $matches[1];
			elseif (!isset($this->fix) &&
					preg_match('/^\s*fix\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->fix = $matches[1];
			elseif (!isset($this->tags) &&
					preg_match('/^\s*tags\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->tags = $matches[1];
			elseif (preg_match('/^\s*category_link\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->category_link = $matches[1];
			elseif (preg_match('/^\s*severity\s*=\s*(.*)/i', $grep_content[$i], $matches)) $this->severity = $matches[1];
			elseif (preg_match('/^\s*description\s*=\s*(.*)/i', $grep_content[$i], $matches)) {
				$this->description = $matches[1];
				for ($j=$i+1; $j<$count; $j++) {
					$desc_line = trim($grep_content[$j]);
					if ($desc_line == "END;") {
						$i = $j + 1;
						break;
					}
					if ($desc_line == "") {
						$desc_line = "<br/><br/>";
					}
					$this->description .= $grep_content[$j] . "\n";
				}
			}
		}
		
		// check for a valid preprocessor
		if (isset($this->preprocess) && !function_exists($this->preprocess)) {
			//@todo This is perhaps only a user notice message rather than a user warning message.
			$yasca->log_message("Unable to find preprocessor function [{$this->preprocess}]. Ignoring.", E_USER_WARNING);
			unset($this->preprocess);
		}
		
		if (!isset($this->valid_file_types)) $this->valid_file_types = array();
		
		$yasca =& Yasca::getInstance();
		$this->severity = $yasca->get_adjusted_severity("Grep", $this->name, $this->severity);
		$this->name = $yasca->get_adjusted_alternate_name("Grep", $this->name, "Grep: " . $this->name);
		
		if (isset($this->pre_grep) && $this->pre_grep == "") unset($this->pre_grep);
	}
}

?>


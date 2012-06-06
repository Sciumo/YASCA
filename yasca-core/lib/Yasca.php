<?php
/**
 * Yasca Class
 *
 * This is the main engine behind Yasca. It handles passed options, scanning for target
 * files and plugins, and executing those plugins. The output of this all is a list of
 * Result objects that can be passed to a renderer.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.2
 * @license see doc/LICENSE
 * @package Yasca
 */
require_once("lib/Plugin.php");
require_once("lib/common.php");
require_once("lib/cache.php");
require_once("lib/Report.php");
require_once("lib/Result.php");

define("VERSION", "2.2");

/**
 * This class implements a generic code scanner.
 * @package Yasca
 */
final class Yasca {
	/**
	 * Options parsed from the command line.
	 * @var array
	 */
	public $options;

	/**
	 * The results of the scan.
	 * @var array of strings
	 */
	public $results = array();

	/**
	 * The target list of files to scan.
	 * @var array of strings
	 */
	public $target_list = array();

	/**
	 * The list of available plugin functions.
	 * @var array of strings
	 */
	public $plugin_list = array();

	/**
	 * This list contains all attachment references to the general cache
	 * @var array
	 */
	public $attachment_list = array();

	/**
	 * @var array of strings
	 */
	public $plugin_file_list = array();

	
	/**
	 * @var callback
	 */
	public $progress_callback = null;

	/**
	 * Adjusted information for various plugins. Used so we don't have to modify
	 * the original plugins.
	 * @var array;
	 */
	private $adjustment_list;

	/**
	 * The general_cache array contains arrays of data to be cached. Read-Write by anyone.
	 */
	public $general_cache = array();

	/**
	 * Usage not yet implemented.
	 * @var Cache
	 */
	public $cache;

	/**
	 * Holds the event array for callbacks
	 * @var array
	 */
	public $event_callback_list = array();
	
	private $max_mem = 268435456; //256M
	
	//http://us3.php.net/manual/en/function.ini-get.php
	private static function calculate_bytes($val) {
	    $val = trim($val);
	    if ($val == "") return null;
	    $last = strtolower($val[strlen($val)-1]);
	    switch($last) {
	        // The 'G' modifier is available since PHP 5.1.0
	        case 'g':
	            $val *= 1024;
	        case 'm':
	            $val *= 1024;
	        case 'k':
	            $val *= 1024;
	    }
	
	    return $val;
	}
	
	/**
	 * @var Yasca
	 */
	private static $instance = null;
	
	/**
	 * Gets the singleton instance of the Yasca object
	 * @param array $options Command line options (parsed). Ignored if Yasca instance already exists.
	 * @todo rename to get_instance for consistency in naming convention
	 */
	public static function &getInstance(array $options = null) {
		if (!isset(static::$instance)) {
			static::$instance = new Yasca($options);
		}
		return static::$instance;
	}	

	/**
	 * Creates a new Yasca scanner object using the options passed in.
	 * If null is passed in, then it will parse the command line options.
	 * @param array $options command line options (parsed)
	 */
	private function __construct(array $options = null) {
		$ini_mem = static::calculate_bytes(ini_get("memory_limit"));
		if (isset($ini_mem)) $this->max_mem = $ini_mem;
		
		// Parse command line arguments if necessary
		if (isset($options) && static::options_have_all_required_keys($options)) {
			$this->options = $options;
		} else {
			$this->parse_command_line_arguments();
		}
		
		$this->ignore_list = $this->parse_ignore_file($this->options['ignore-file']);
		
		$this->cache = new Cache(33554432);     // 32 meg cache
		
		static::$instance =& $this;

		// Scan for target files
		$this->log_message("Scanning for files...", E_USER_NOTICE);
		if (is_file($this->options['dir'])) {       // Allow user to specify a single file
			$this->target_list = array($this->options['dir']);
		} else {
			$this->target_list = $this->dir_recursive($this->options['dir']);
			if (!is_array($this->target_list)) {
				$this->log_message("Invalid target directory specified.", E_USER_ERROR);
			}
			$ignore_list = $this->options['ignore-ext'];
			$max_filesize = $this->max_mem / 3;
			$this->target_list = array_filter($this->target_list, function ($target) use ($ignore_list, $max_filesize){
				$pinfo = pathinfo($target);
				if (isset($pinfo['extension']) && in_array($pinfo['extension'], $ignore_list))
					return false;
				
				if (!file_exists($target) || !is_readable($target)) {
					Yasca::log_message("Unable to read [$target]. File will be ignored.", E_USER_WARNING);
					return false;
				}
				if (filesize($target) >= $max_filesize){
					Yasca::log_message("$target is too big to load into Yasca. Skipping...", E_USER_WARNING);
					return false;
				}
				return true;
			});
		}

		// Scan for plugins
		$this->log_message("Scanning for plugins...", E_USER_NOTICE);
		$this->include_plugins($this->options['plugin_dir']);
		if (!is_array($this->plugin_file_list)) {
			$this->log_message("Invalid plugin directory specified.", E_USER_ERROR);
		}
		
		//By default, remove ignored findings after a scan, 
		//clense the full paths from filenames,and then sort.
		$this->register_callback("post-scan", function () {
			$yasca =& Yasca::getInstance();
	
			if (!isset($yasca->results) || !is_array($yasca->results) || $yasca->results == NULL) {
				$yasca->log_message("No results were found.", E_ALL);
				return;
			}
			
			//Filter out the full pathnames
			foreach ($yasca->results as &$result){
				$result->filename = str_replace($yasca->options['dir'], "", correct_slashes($result->filename));
			}
			
			//Filter out unique results
			$yasca->results = array_unique_with_selector($yasca->results, function ($result) {
				return "$result->filename->$result->line_number->$result->category->$result->severity->$result->source";
			});

			//Filter out the ignored files
			$yasca->results = array_filter($yasca->results, function ($result) use (&$yasca){
				foreach ($yasca->ignore_list as $ignore) {
					if (	$ignore->line_number == $result->line_number &&
							$ignore->category == $result->category &&
							$ignore->filename == $result->filename)
						return false;
				}
				return true;
			});
			
			//Sort the results
			usort($yasca->results, function($a, $b){ 
				if (!is_object($a) || !is_object($b)) return 0;
	
				if ($a->severity != $b->severity)
					return ($a->severity < $b->severity ? -1 : 1);
					
				if ($a->category != $b->category)
					return ($a->category < $b->category ? 1 : -1);
				
				if ($a->filename != $b->filename)
					return ($a->filename < $b->filename ? 1 : -1);
					
				return ($a->line_number < $b->line_number ? -1 : 1);
			});
		});
	}

	


	/**
	 * This function initiates the scan. After checking various things, it passes
	 * execution along to each of the plugins available, on each of the target files
	 * available.
	 * Writes output to $this->results.
	 * @return nothing
	 */
	public function scan() {
		if (!is_array($this->target_list) || count($this->target_list) == 0) {
			$this->log_message("No files were found to scan. Nothing to do.", E_USER_ERROR);
			return;
		}
		if (!is_array($this->plugin_list) || count($this->plugin_list) == 0) {
			$this->log_message("No plugins were found. Nothing to do", E_USER_ERROR);
			return;
		}

		$total_executions = count($this->target_list) * count($this->plugin_list);
		$num_executions = 0;
		
		foreach ($this->target_list as $target) {
			$this->log_message("Attempting to scan [$target]", E_ALL);
			$target_file_contents = null; //Lazy load, but keep a handle around.
			
			foreach ($this->plugin_list as $key => $plugin) {
				$this->log_message("Initializing plugin [$plugin] on [$target]", E_USER_NOTICE);
				
				$plugin_obj = new $plugin($target, $target_file_contents);
				if (!$plugin_obj->initialized) {
					$this->log_message("Unable to instantiate plugin object of [$plugin] class. Plugin will be ignored.", E_USER_WARNING);
					$total_executions -= count($this->target_list);
					unset($this->plugin_list[$key]);
					continue;
				}
				
				if (!isset($target_file_contents) && !$plugin_obj->is_multi_target 
							&& $plugin_obj->is_valid_filetype){
					// This is a slow process - is there a faster alternative?
					//FILE_TEXT switch requires PHP 6 to function.
					$target_file_contents = file($target, FILE_TEXT+FILE_IGNORE_NEW_LINES);
					/* @todo Global unicode support requires compiling PHP with the mbstring module.
					 * http://us2.php.net/manual/en/mbstring.installation.php
					 * Note that enabling this line will render the
					 * conversion calls in Grep.php and PotentialConcerns.php incorrect; they must be 
					 * removed when this is enabled.
					 * $target_file_contents = mb_convert_encoding($target_file_contents, 'ISO-8859-1', 
						"ASCII,JIS,UTF-8,EUC-JP,SJIS,UTF-16"); */
					$plugin_obj->file_contents = $target_file_contents;
				}
				$plugin_obj->run();
				
				if ($this->options['verbose'])
					$this->log_message("Plugin [$plugin] returned " . count($plugin_obj->result_list) . " results. ", E_USER_NOTICE);

				if (is_callable($this->progress_callback)) {
					call_user_func($this->progress_callback, array("progress", ((100 * ++$num_executions) / $total_executions)));
				}
				
				$this->results = array_merge($this->results, $plugin_obj->result_list);

				
				if ($this->options['debug']) {
					$this->log_message("Memory Usage: [" . memory_get_usage(true) . "] after calling [$plugin] on [$target]", E_ALL);
				}
			}
		}
	}

	/**
	 * Finds and includes all plugins under the given directory.
	 * Writes output to $this->plugin_list.
	 * @param string $plugin_directory directories (comma delimited) to look for plugins in (recursively).
	 * @return nothing
	 */
	private function include_plugins($plugin_dir = "plugins") {
		$plugin_dir_list = explode(",", $plugin_dir);
		$plugin_file_list = array();

		// Find the plugins
		foreach ($plugin_dir_list as $plugin_item) {
			$plugin_item = trim($plugin_item);
			if (is_file($plugin_item)) {
				$plugin_file_list[] = $plugin_item;
			} elseif (is_dir($plugin_item)) {
				foreach ($this->dir_recursive($plugin_item) as $plugin_item) {
					$plugin_file_list[] = $plugin_item;
				}
			}
		}

		// include() each of the plugins
		foreach ($plugin_file_list as $plugin_file) {
			$pinfo = pathinfo($plugin_file);
			$base = (isset($pinfo['basename']) ? $pinfo['basename'] : "");

			// ignore all plugin files that start with a '_'
			if (startsWith($base, "_")) continue;
			
			$ext = (isset($pinfo['extension']) ? $pinfo['extension'] : "");
			if (strtolower($ext) === 'php') {
				foreach ($this->options['plugin_exclude'] as $excluded_plugin) {
					if ($excluded_plugin != '' &&
					preg_match('/' .$excluded_plugin . '/i', $plugin_file)) {
						$this->log_message("Excluding plugin file [$excluded_plugin][$plugin_file].", E_USER_NOTICE);
						continue 2;
					}
				}
				$class_name = get_class_from_file($plugin_file);
				if ($class_name === false) {
					$this->log_message("Unable to find class in plugin file [$plugin_file]. Ignoring.", E_USER_WARNING);
				}  else {
					$this->log_message("Including plugin file [$plugin_file].", E_USER_NOTICE);
					include_once($plugin_file);
					if (!class_exists($class_name) || 
						!is_subclass_of($class_name, "Plugin")){
						$this->log_message("Found $class_name in plugin file [$plugin_file], but it is not a Plugin.", E_USER_WARNING);
					}
					else{
						//It is a valid plugin, add it to the list.
						$this->plugin_list[] = $class_name;
					}
				}
			}

			// This holds all files possible for plugins, not just .php files
			$this->plugin_file_list[] = $plugin_file;
		}
	}

	/**
	 * This is the main error log and event log for the application.
	 * The output is directed based on whether the application is running
	 * as a GUI or in console mode. If the Yasca object is not defined, 
	 * or $just_print is set to true, then this will do a simple print().
	 * If a message of E_USER_ERROR severity is received, this will call exit(1).
	 * 
	 * E_USER_ERROR and E_USER_WARNING messages are printed by default.
	 * If --verbose is true, E_USER_NOTICE messages are also printed.
	 * If --debug is true, E_USER_NOTICE and E_USER_ALL messages are also printed.
	 * If --silent is true, this function will not print any messages, regardless of other settings.
	 * @param integer $severity severity associated with the message. Accepted values are E_USER_NOTICE, E_USER_ERROR, E_USER_WARNING, or E_ALL.
	 * @param string $message message to write
	 * @param boolean $just_print Whether to just print() every message, regardless of severity.
	 * @param boolean $include_timestamp if true, prepend a timestamp to the message before printing.
	 * @return nothing
	 */
	//Shouldn't $just_print be deprecated if it's essentially replaced by --debug?
	public static function log_message($message, $severity = E_USER_NOTICE, $include_timestamp = false, $just_print = false) {
		if (!$message || trim($message) == '') return;
		if (substr($message, -1) != "\n") $message .= "\n";

		switch($severity) {
		    case E_USER_NOTICE:
		        $msgPrefix = "INFO  "; break;
		    case E_USER_WARNING:
		        $msgPrefix = "WARN  "; break;
		    case E_USER_ERROR:
		        $msgPrefix = "ERROR "; break;
		    case E_ALL:
		        $msgPrefix = "INFO  "; break;
		    default:
		        $msgPrefix = "INFO  "; break;
		}
		
		if ($include_timestamp) {
			$message = date('Y-m-d h:i:s ') . $message;
		}

		if (isset(static::$instance)) {
			$yasca =& static::$instance;
			if ($yasca->options['silent']){
				if ($severity == E_USER_ERROR) exit(1);
				return;
			}
		} else {
			print $msgPrefix . $message;
			return;
		}

		if ($just_print) {
			print $msgPrefix . $message;
			return;
		}

		if (  	$severity == E_USER_ERROR ||
				$severity == E_USER_WARNING ||
				($severity == E_USER_NOTICE && $yasca->options['verbose']) ||
				($severity == E_ALL && $yasca->options['debug']) 
																) {

			// use a user-defined callback function for the log
			if (isset($yasca->progress_callback) && is_callable($yasca->progress_callback)) {
				call_user_func($yasca->progress_callback, array("log_message", $message));
			} else {
				print $msgPrefix . $message;
			}
				
			// Log to a file as well?
			if ($yasca->options['log'] !== false && is_file($yasca->options['log'])) {
				$d = date('Y-m-d h:i:s ');
				file_put_contents($yobj->options['log'], $msgPrefix . $d . $message, 'FILE_APPEND');
			}
			
			if ($severity == E_USER_ERROR) {
				$yasca->log_message("Execution aborted due to error.", E_USER_WARNING);
				exit(1);
			}
		}
		else {
			//Ignore the message without printing anything.
		}		
	}
	
	/**
	 * Parses the command line arguments (argc, argv).
	 * Will call exit(1) if the --help or --version switches are encountered.
	 * @param boolean $parse_arguments actually parse arguments or use the default?
	 * @return array of options.
	 */
	public static function parse_command_line_arguments($parse_arguments = true) {
		//Get defaults for arguments.
		$opt = array();
		$opt['dir'] = ".";
		$opt['plugin_dir'] = "./plugins"; //@todo move this over to plugin.php
		$opt['plugin_exclude'] = "";    // will be filled in later and exploded(",") to array
		$opt['level'] = false;
		$opt['verbose'] = false;
		$opt['source_required'] = false;
		$opt['output'] = Report::default_dir();
		$opt['ignore-ext'] = "exe,zip,jpg,gif,png,pdf";
		$opt['ignore-file'] = false;
		$opt['sa_home'] = isset($_ENV['SA_HOME']) ? $_ENV['SA_HOME'] : ".";
		$opt['report'] = Report::default_type;
		$opt['silent'] = false;
		$opt['debug'] = false;
		$opt['log'] = false;
		$opt['fixes'] = false;
		$opt['parameter'] = array();    // will be filled in by -d options

		$args = $parse_arguments ? $_SERVER["argc"] : 0;
		
		if ($args == 1) {
            print("Yasca-Core version " . constant("VERSION") . "\n");
            print("Copyright (c) 2010 Michael V. Scovetta. See docs/LICENSE for license information.\n\n");
			print(static::help());
			exit(1);
		}
		
		//Assign the values for arguments passed in.
		for ($i = 1; $i < $args; $i++) {
			switch($_SERVER["argv"][$i]) {
				case "-v":
				case "--version":
					print("Yasca-Core version " . constant("VERSION") . "\n");
					print("Copyright (c) 2010 Michael V. Scovetta. See docs/LICENSE for license information.\n");
					exit(1);
					break;

				case "-h":
				case "--help":
					print(static::help());
					exit(1);
					break;

				case "-d":      /* Pass this parameter to underlying components */
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					parse_str($_SERVER['argv'][$i], $opt['parameter']);
					break;

				case "--debug":
					$opt['debug'] = true;
					//Debug inherits verbose; the lack of break is intentional.
					
				case "--verbose":
					$opt['verbose'] = true;
					break;

				case "--log":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['log'] = $_SERVER['argv'][$i];
					break;

				case "-i":
				case "--ignore-ext":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['ignore-ext'] = $_SERVER['argv'][$i];
					break;

				case "--ignore-file":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['ignore-file'] = $_SERVER['argv'][$i];
					break;

				case "-o":
				case "--output":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['output'] = $_SERVER['argv'][$i];
					break;

				case "-f":
				case "--fixes":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['fixes'] = $_SERVER['argv'][$i];
					break;

				case "-sa":
				case "--sa_home":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['sa_home'] = $_SERVER['argv'][$i];
					break;

				case "--source-required":
					$opt['source_required'] = true;
					break;

				case "-p":
				case "--plugin":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['plugin_dir'] = $_SERVER['argv'][$i];
					break;

				case "-px":
				case "--plugin-exclude":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['plugin_exclude'] = $_SERVER['argv'][$i];
					break;

				case "-r":
				case "--report":
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['report'] = $_SERVER['argv'][$i];
					break;

				case "-s":
				case "--silent":
					$opt['silent'] = true;
					break;

				case "-l":
				case "--level";
					if (!isset($_SERVER['argv'][++$i])){
						print(static::help());
						exit(1);
					}
					$opt['level'] = $_SERVER['argv'][$i];
					break;

				default:
					$opt['dir'] = $_SERVER['argv'][$i];
			}
		}

		//Validate arguments
		if ($opt['report'] == "MySQLReport" && count($opt['parameter']) == 0) {
			if (!$opt['silent']) {
				print("MySQLReport specified, but database connection details not passed. Aborting.");
			}
			exit(1);
		}
		
	    if (!is_numeric($opt['level']) ||
            intval($opt['level']) < 1 ||
            intval($opt['level']) > 5) {
            $opt['level'] = 5;
        }

		$opt['ignore-ext'] = str_replace(" ", "", $opt['ignore-ext']);
		$opt['ignore-ext'] = $opt['ignore-ext'] == "0" ? array() : explode(",", $opt['ignore-ext']);
		
		$opt['dir'] = realpath($opt['dir']);

		$opt['sa_home'] = correct_slashes($opt['sa_home'], true);

		$opt['plugin_exclude'] = explode(",", $opt['plugin_exclude']);

		return $opt;
	}
	
	private static function options_have_all_required_keys(array $options){
		$required_keys = array('dir', 'plugin_dir', 'plugin_exclude',
								'level', 'verbose', 'source_required',
								'output', 'ignore-ext', 'ignore-file',
								'sa_home', 'report', 'silent', 'debug',
								'log', 'fixes', 'parameter');
		foreach($required_keys as $key){
			if (!isset($options[$key])) return false;
		}
		return true;
	}

	/**
	 * Returns the help message.
	 * @return string content of the help message (aka usage)
	 */
	public static function help() {
		//@todo How do you use constants with a heredoc?
		$default_report = Report::default_type;
		
		return <<<END
Usage: yasca [options] directory
Perform analysis of program source code.

      --debug               additional debugging
  -d "QUERYSTRING"          pass the query string to Yasca's sub-components
  -h, --help                show this help
  -i, --ignore-ext EXT,EXT  ignore these file extensions 
                              (default: exe,zip,jpg,gif,png,pdf,class)
      --ignore-file FILE    ignore findings from the specified xml file
      --source-required     only show findings that have source code available
  -f, --fixes FILE          include fixes, written to FILE (default: not included)
                              (EXPERIMENTAL)
  -l, --level LEVEL         show findings at least LEVEL (1-5) (default: 5=all)
  -o, --output FILE         write output to FILE (default: unique file on
                              desktop in Yasca directory)
  -p, --plugin DIR|FILE     load plugins from DIR or just load FILE (default: ./plugins)
  -px PATTERN[,PATTERN...]  exclude plugins matching PATTERN or any of "PATTERN,PATTERN"
                              (multiple patterns must be enclosed in quotes)
      --log FILE            write log entries to FILE
  -sa,--sa_home DIR         use this directory for 3rd party plugins (default: \$SA_HOME)
  -r, --report REPORT       use REPORT template (default: $default_report). Options
                              include HTMLGroupReport, CSVReport, XMLReport, SQLReport, 
                              DetailedReport, and ConsoleReport. 
  -s, --silent              do not show any output
  -v, --version             show version information

Examples:
  yasca c:\\source_code
  yasca /opt/dev/source_code
  yasca -px FindBugs,PMD,Antic,JLint /opt/dev/source_code
  yasca -o c:\\output.csv --report CSVReport "c:\\foo bar\\quux"
  yasca -d "SQLReport.database=./my.db" -r SQLReport /opt/dev/source_code

END;
	}
	
	/**
	 * Recursive directory listing. Returns all files starting
	 * at $start_dir.
	 * @param string $start_dir starting directory (default=.)
	 * @return array of filenames or false if the input is not a directory
	 */
	public static function dir_recursive($start_dir='.') {
		$files = array();
		$start_dir = correct_slashes($start_dir);    // canonicalize
		if (is_dir($start_dir)) {
			$fh = opendir($start_dir);
			while (($file = readdir($fh)) !== false) {
				if (strcmp($file, '.')==0 || strcmp($file, '..')==0) continue;
					$filepath = $start_dir . DIRECTORY_SEPARATOR . $file;
				if ( is_dir($filepath) )
					$files = array_merge($files, static::dir_recursive($filepath));
				else
					$files[] = $filepath;
			}
			closedir($fh);
		} else {
			$files = false;
		}
		return $files;
	}

	/**
	 * Attempts to find a target that matches the relative name supplied.
	 * @param string $rel_filename filename to search for
	 * @return boolean true iff target found.
	 */
	public function find_target_by_relative_name($rel_filename) {
		$rel_filename = correct_slashes($rel_filename);
		foreach ($this->target_list as $target) {
			if (endsWith(correct_slashes($target), $rel_filename)) {
				return $target;
			}
		}
		return false;
	}
	
	/**
	 * Instantiates and executes a report of type $report_type, or if not set, the type specified by --report.
	 * @param string $report_type The type of report to generate. Defaults to the type defined by the --report switch.
	 * @returns Report An instantiated and executed report.
	 * @todo Instead simply go straight to Report::instantiate_report();
	 */
	public function &instantiate_report($report_type = null){
		if (!isset($report_type)){
			$report_type = $this->options['report'];
		}
		$report = Report::instantiate_report($report_type);
		return $report;
	}

	/**
	 * @param string $filename
	 * @return array of StdClass containing fields filename, line_number, and category. Can be empty array.
	 */
	private static function parse_ignore_file($filename) {
		if (!file_exists($filename) || !is_readable($filename)) return array();
		$dom = new DOMDocument();
		if (!@$dom->load($filename)) return array();
		$elts = $dom->getElementsByTagName("ignore");
		$ig_list = array();
		foreach ($elts as $elt) {
			$ig = new StdClass;
			$ig->filename = correct_slashes($elt->getAttribute("filename"));
			$ig->line_number = $elt->getAttribute("line_number");
			$ig->category = $elt->getAttribute("category");
			$ig_list[] = $ig;
		}
		return $ig_list;
	}

	/**
	 * Loads all of the adjustments from resources/adjustments.xml.
	 * Writes outputs to $this->adjustment_list
	 * @return nothing
	 */
	private function load_adjustments() {
		$dom = new DOMDocument();
		if (!@$dom->loadXML( file_get_contents("resources".DIRECTORY_SEPARATOR."adjustments.xml"))) {
			$this->log_message("Unable to load plugin adjustments. Defaults will be used.", E_USER_WARNING);
			$this->adjustment_list = array();
			return;
		}

		foreach ($dom->getElementsByTagName("adjustment") as $adjustment) {
			$plugin_name = $adjustment->getAttribute("plugin_name");
			$finding_name = $adjustment->getAttribute("finding_name");
			$severity = $adjustment->getAttribute("severity");
			$alternate_name = $adjustment->getAttribute("alternate_name");
			$description = $adjustment->getElementsByTagName("description");
			$description_method = "";

			if ($description->length > 0) {
				$description_method = $description->item(0)->getAttribute("method");
				$description = $description->item(0)->nodeValue;
			} else {
				unset($description);
				unset($description_method);
			}

			$key = str_replace(" ", "", strtolower("$plugin_name.$finding_name"));

			if (preg_match('/([+\-]?)(\d+)/', $severity, $matches)) {
				if ($matches[1] == "+") $severity_method = "increase";
				else if ($matches[1] == "-") $severity_method = "decrease";
				else $severity_method = "set";
				$severity = intval($matches[2]);
			}

			if (isset($severity_method)) $this->adjustment_list["$key.severity.method"] = $severity_method;
			if (isset($severity)) $this->adjustment_list["$key.severity.amount"] = $severity;
			if (isset($description)) $this->adjustment_list["$key.description.text"] = $description;
			if (isset($alternate_name)) $this->adjustment_list["$key.alternate_name.text"] = $alternate_name;
			if (isset($description_method)) $this->adjustment_list["$key.description.method"] = $description_method;
		}
	}

	/**
	 * Retrieves a specific adjustment.
	 * @param string $key key use look up
	 * @param string $default_value default value, if $key does not exist.
	 * @see $this->adjustment_list
	 */
	public function get_adjustment($key, $default_value) {
		$key = str_replace(" ", "", strtolower($key));
		if (!isset($this->adjustment_list) ||
		!isset($this->adjustment_list[$key])) {
			return $default_value;
		}
		return $this->adjustment_list[$key];
	}

	/**
	 * Gets the adjusted description of the finding.
	 * @param string $plugin_name plugin name used
	 * @param string $finding_name finding name
	 * @param string $description current description of the finding
	 */
	public function get_adjusted_description($plugin_name, $finding_name, $description) {
		if (!isset($this->adjustment_list)) {
			$this->load_adjustments();
		}
		$description_text = $this->get_adjustment("$plugin_name.$finding_name.description.text", "");
		$description_method = $this->get_adjustment("$plugin_name.$finding_name.description.method", "");

		if ($description_method == "append") {
			return $description . $description_text;
		} else if ($description_method == "prepend") {
			return $description_text . $description;
		} else {
			return ($description_text == "" ? $description : $description_text);
		}
	}

	/**
	 * Gets the adjusted severity for a specific plugin.
	 * @return int {1,5}
	 */
	public function get_adjusted_severity($plugin_name, $finding_name = "", $severity = 5) {
		if (!isset($this->adjustment_list)) {
			$this->load_adjustments();
		}
		$severity_amount = $this->get_adjustment("$plugin_name.$finding_name.severity.amount", -1);
		$severity_method = $this->get_adjustment("$plugin_name.$finding_name.severity.method", "set");

		if ($severity_method == "set") {
			$new_severity = ($severity_amount != -1 ? $severity_amount : $severity);
		} else if ($severity_method == "increase") {
			$new_severity = $severity + $severity_amount;
		} else if ($severity_method == "decrease") {
			$new_severity = $severity - $severity_amount;
		}

		if (intval($new_severity) > 5) return 5;
		if (intval($new_severity) < 1) return 1;
		return intval($new_severity);
	}

	/**
	 * Gets the adjusted alternate name for a specific plugin.
	 * @param string $plugin_name plugin name referenced in adjustments.xml
	 * @param string $finding_name the "message" that is used in that line
	 * @param string $default_text the default text to show, if nothing was defined in $adjustments.xml (optional)
	 */
	public function get_adjusted_alternate_name($plugin_name, $finding_name = "", $default_text = "") {
		if ($plugin_name == "") {
			$this->log_message("No plugin name passed to get_adjusted_alternate_name (finding_name=$finding_name)", E_USER_WARNING);
		}
		if (!isset($this->adjustment_list)) {
			$this->load_adjustments();
		}
		return $this->get_adjustment("$plugin_name.$finding_name.alternate_name.text", $default_text);
	}

	/**
	 * Adds an attachment to the attachment list. Only allows attachment that are represented in
	 * the general cache.
	 * @param string $cache_id 
	 * @return nothing
	 */
	public function add_attachment($cache_id) {
		if (!isset($this->general_cache[$cache_id])) {
			$this->log_message("Unable to find cache [$cache_id] in the general cache.", E_USER_WARNING);
			return;
		}
		if (!in_array($cache_id, $this->attachment_list)) {
			array_push($this->attachment_list, $cache_id);
		}
	}

	/**
	 * Gets the attachment by $cache_id. If the id is not in the cache, return false.
	 * @param $cache_id
	 * @return mixed The item in the cache with the specified id or false if it does not exist.
	 */
	public function get_attachment($cache_id) {
		if (!isset($this->general_cache[$cache_id])) {
			return false;
		}
		return $general_cache[$cache_id];
	}

	/**
	 * Registers a callback function to be executed at some time. Valid events are:
	 * pre-scan    - executes before the scan takes place
	 * post-scan   - executes after scan() completes
	 * pre-report  - executes before the reporting occurs
	 * post-report - executes after the reporting occurs
	 * @return nothing
	 */
	public function register_callback($event, $func) {
		if (!isset($this->event_callback_list[$event])) {
			$this->event_callback_list[$event] = array();
		}
		$callback_list = $this->event_callback_list[$event];
		if (!is_array($callback_list)) {
			$callback_list = array();
		}

		if (!in_array($func, $callback_list)) {
			array_push($callback_list, $func);
		}

		$this->event_callback_list[$event] = $callback_list;
	}

	/**
	 * Executes callbacks for a particular event.
	 * @param string $event {"pre-scan", "post-scan", "pre-report", "post-report"}
	 * @return nothing
	 */
	public function execute_callback($event) {
		if (!isset($this->event_callback_list[$event])) return;

		$callback_list = $this->event_callback_list[$event];
		if (!is_array($callback_list)) {
			$this->log_message("Executing callbacks for event [$event] but no callbacks are registered", E_USER_NOTICE);
			return;
		}

		foreach ($callback_list as $callback) {
			call_user_func($callback);
		}
	}

	/**
	 * Place a small advertisement within Yasca.
	 * @param string $type Returns a hyperlinked email address if =="HTML", ignored otherwise. Defaults to "HTML".
	 * @return string The advertisement
	 * @todo rename to get_advertisement_text for consistency in naming convention
	 */
	public static function getAdvertisementText($type="HTML") {
		if ($type == "HTML") {
			$ad = "Commercial support is now available for Yasca. Contact <a href=\"mailto:scovetta@users.sourceforge.net\">scovetta@users.sourceforge.net</a> for more information.";
		} else {
			$ad = "Commercial support is now available for Yasca. Contact scovetta@users.sourceforge.net for more information.";
		}
		return $ad;
	}
	
/**
	 * Signs a piece of data using a hash. Uses SHA-1 to hash the data.
	 * @param string $data string to hash.
	 * @param string $password salt used in the calculation.
	 * @return hash of the data.
	 * @todo Do not allow use of hardcoded password.
	 */
	public static function calculate_signature($data, $password="3A4B3f39jf203jfALSFJAEFJ30fn2q3lf32cQF3FG") {
		$data = str_replace(array(chr(10), chr(14)), "", $data);
		return sha1($password . $data . $password);
	}

	/**
	 * Validates if a signature has been tampered with. Uses calculate_signature() to
	 * re-calculate the signature.
	 * @param string $data string to hash
	 * @param string $signature purported signature
	 * @param string $password salt used in the calculation.
	 * @return boolean true iff the signature matches the expected.
	 * @todo Do not allow use of hardcoded password.
	 */
	public static function validate_signature($data, $signature, $password="3A4B3f39jf203jfALSFJAEFJ30fn2q3lf32cQF3FG") {
		return ($signature == calculate_signature($data, $password));
	}

	/**
	 * Validates whether a report content has a valid hash.
	 * @param string $data data to check (the report content)
	 * @return true iff the signature matches the expected.
	 */
	public static function validate_report($data) {
		if (!$data || strlen($data) == 0) return false;
		$signature = substr($data, strlen($data)-62);
		$matches = array();
		if (!preg_match("/<\!-- SIGNATURE: \[[a-z0-9]+\] -->/", $signature, $matches)) return false;
		$signature = $matches[1];                       // just the hash
		$data = substr($data, 0, strlen($data)-62);     // pull out the signature
		return static::validate_signature($data, $signature);
	}
}
?>

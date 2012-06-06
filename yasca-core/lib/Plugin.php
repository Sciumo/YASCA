<?php
require_once("lib/Result.php");
require_once("lib/common-analysis.php");

/**
 * Plugin Class
 *
 * This (abstract) class is the parent of all plugin classes.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
abstract class Plugin {
	/**
	 * Holds the filename that this Plugin happens to be working on.
	 * @var string
	 */
	public $filename = "";

	/**
	 * Holds the file contents that this Plugin is working on.
	 * @var array of strings
	 */
	public $file_contents = array();
	
	/**
	 * Holds the results of the scan.
	 * @var array of Result()s
	 */
	public $result_list = array();

	/**
	 * Valid file types that this Plugin can operate on - ie, array("exe","bin")
	 * @var array of strings
	 */
	public $valid_file_types = array();

	/**
	 * True iff this object was initialized (i.e. has a valid extension)
	 * @var boolean
	 */
	public $is_valid_filetype = false;

	/**
	 * How many lines to include in the context returned.
	 * @var integer
	 */
	public $context_size = 7;


	/**
	 * Description of this plugin (what it looks for, why it's important, how to
	 * remediate.
	 * @var string
	 */
	public $description = "default";

	/**
	 * True iff this object is to be only invoked once. The object itself should prevent
	 * multiple executions.
	 * @var boolean
	 */
	public $is_multi_target = false;

	/**
	 * Internal variable set to true at the end of the constructor.
	 * @var boolean
	 */
	public $initialized = false;

	/**
	 * This file should exist to indicate that the underlying plugin is accessible, or should
	 * be true to mean ignore it.
	 * @var boolean
	 */
	public $installation_marker = true;

	/**
	 * This is a reference to the static analyzers plugin directory
	 * @var string representing a filepath
	 */
	public $sa_home = "";

	/**
	 * This sometimes contains the executable to be called.
	 * @var array
	 */
	public $executable = array();

	/**
	 * Interval marker used to prevent objects from being executed.
	 * @var boolean
	 * @todo Rename can_execute
	 */
	public $canExecute = true;

	private static $ext_classes = array( "JAVA"       => array("java", "jsp", "jsw"),
	                                     "JAVASCRIPT" => array("js", "html", "css", "js", "htm", "hta"),
                                         "C"          => array("c", "cpp", "h", "cxx", "cc", "c++"),
                                         "HTML"       => array("html", "css", "js", "htm", "hta"),
                                         "BINARY"     => array("dll", "zip", "jar", "ear", "war", "exe"),
                                         "PHP"        => array("php", "php5", "php4"),
                                         "NET"        => array("aspx", "asp", "vb", "frm", "res", "cs", "ascx"),
                                         "COBOL"      => array("cobol", "cbl", "cob"),
                                         "PERL"       => array("pl", "cgi"),
                                         "PYTHON"     => array("py"),
                                         "COLDFUSION" => array("cfm", "cfml"),
	                                     "RUBY"       => array("rb")
	);
	
	/**
	 * Creates a new generic Plugin.
	 * @param string $filename that is being examined.
	 * @param mixed $file_contents array or string of the file contents.
	 */
	public function __construct($filename, $file_contents){
		$yasca =& Yasca::getInstance();
		$this->sa_home = $yasca->options["sa_home"];

		if (static::check_in_filetype($filename, $this->valid_file_types)) {
			$this->is_valid_filetype = true;
			//Multitarget plugins use the directory rather than a specific filename or contents.
			if (!$this->is_multi_target){
				$this->filename = $filename;
				$this->file_contents = $file_contents;
			}
		}

		$this->initialized = true;
	}


	/**
	 * @deprecated Use __construct instead
	 */
	public function Plugin($filename, $file_contents) {
		$this->__construct($filename, $file_contents);
	}

	/**
	 * This function is called to de-allocate as much of the object as possible.
	 * @deprecated Wasteful of cpu time to use as of PHP 5.3.0 and it's new garbage collector.
	 */
	public function destructor() {
		foreach ($this as $item) {
			$item = null;
			unset($item);
		}
	}

	/**
	 * This function should not be called, since this class is abstract. The execute() function
	 * should be overridden by child classes.
	 */
	public abstract function execute();


	/**
	 * Starts execution of the specific plugin. Calls the overridden method of child classes
	 * to perform the scan. This function just wraps that.
	 */
	public function run() {
		if (!$this->initialized) return false;
		if ((!$this->is_multi_target && !$this->is_valid_filetype) || !$this->canExecute) return false;

		/* These sections handle the installation detection for plugins */
		$yasca =& Yasca::getInstance();
		$no_execute =& $yasca->general_cache["no_execute"];
		if (!is_array($no_execute)) {
			$no_execute = array();
			$yasca->general_cache["no_execute"] =& $no_execute;
		}

		if ($this->installation_marker !== true) {	// installation_marker == true means, "no plugin installation needed"
			if (isset($no_execute[$this->installation_marker]) &&
				$no_execute[$this->installation_marker] === true) {
				return false;
			}
			
			if (!file_exists($yasca->options['sa_home'] . "resources/installed/" . $this->installation_marker)) {
				$yasca->log_message("Plugin \"{$this->installation_marker}\" not installed. Download it at yasca.org.", E_USER_WARNING);

				$no_execute[$this->installation_marker] = true;		// add to the cache so this only happens once
				return false;
			}
		}

		$this->execute();

		if (!is_array($this->result_list)) {
			$yasca->log_message("Unable to process results list from ".get_class($this).".", E_USER_WARNING);
			return;
		}
		foreach($this->result_list as &$result){
			if (!isset($result->filename)) $result->filename = $this->filename;
			if (!isset($result->severity)) $result->severity = 5;
			if (!isset($result->is_source_code)) $result->is_source_code = false;
			if (!isset($result->category)) $result->category = "General";
			if (!isset($result->category_link)) $result->category_link = "";
			if (!isset($result->plugin_name)) $result->plugin_name = get_class($this);
			if (!isset($result->description)) $result->description = "";
			if (!isset($result->source_context) &&
				isset($this->file_contents)) $result->source_context = array_slice( $this->file_contents, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
			if (!isset($result->source)) {
				if ($result->line_number > 0 &&
					isset($this->file_contents)) {
					$result->source = $this->file_contents[ $result->line_number-1 ];
				} else {
					$result->source = "";
				}
			}
		}
	}

	/**
	 * Checks for the current version of Java. 
	 * @param float $minimum_version The minimum version of java to accept. Defaults to 1.4
	 * @return boolean If the version of java is at least the specified minimum version
	 */
	protected final static function check_for_java($minimum_version = 1.40) {
		$result = array();
		exec("java -version 2>&1", $result);
		if (!isset($result[0])) return false;
		if (stripos($result[0], "is not recognized") !== false) return false;
		$matches = array();
		if (preg_match("/\"(\d+\.\d+)/", $result[0], $matches)) {
			$version = $matches[1];
			return (floatval($version) >= floatval($minimum_version));
		}
		return false;
	}

	/**
	 * Checks to see if the given filename has a passed extension.
	 * $filename does not have to be an accessible file.
	 * @param string $filename filename to check
	 * @param mixed $ext string extension or array of extensions to check. Should not include a period. An empty array means accept all filenames.
	 * @return boolean true iff filename matches one of the extensions or if $ext was an empty array.
	 * @todo This function is still too slow (sometimes slower than even all of grep.php)
	 */
	protected final static function check_in_filetype($filename, $exts = array(), array $equiv_classes = null) {
		if (!is_array($exts)) $exts = explode(",", $exts);

		if (count($exts) == 0) return true;      // $ext=() means all accepted
		
		$file_ext = strrchr($filename, ".");
		if ($file_ext == false) return false;
		$file_ext = substr($file_ext, 1);
		
		//Because this is a bottlenecking function, check the most common usage first and return early.
		//Stage 1: the caller provided the exact list of extensions that they are interested in.
		if (in_array($file_ext, $exts)) return true;
		
		//Stage 2: The caller provided an equivalent class token, ie "JAVA" or "COBOL".
		if (!isset($equiv_classes)) $equiv_classes = self::$ext_classes;

		foreach (array_intersect($exts, array_keys($equiv_classes)) as $ev) {
			if (in_array($file_ext,$equiv_classes[$ev])) return true;
		}

		//@todo Which plugins only ask for specific files by their full name? This is very CPU time expensive.
		foreach ($exts as $ext){
			if (fnmatch($ext, $filename)) return true;
		}
		return false;
	}

	/**
	 * Replaces standard variables from executable strings.
	 * @param string $executable string to expand out
	 * @return new string with special variables replaced
	 * @todo rename to replace_executable_strings for consistency in naming convention
	 */
	public final function replaceExecutableStrings($executable) {
		$executable = str_replace("%SA_HOME%", $this->sa_home, $executable);
		foreach ($_ENV as $key => $value) {
			$executable = str_replace("%$key%", $value, $executable);
		}
		return $executable;
	}

}
?>

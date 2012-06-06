<?php
/**
 * Yasca Class
 *
 * This is the main engine behind Yasca. It handles passed options, scanning for target
 * files and plugins, and executing those plugins. The output of this all is a list of 
 * Result objects that can be passed to a renderer.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.3
 * @package Yasca
 */
include_once("lib/Plugin.php");
include_once("lib/common.php");
include_once("lib/cache.php");

define("VERSION", "1.3");

/**
 * This class implements a generic code scanner.
 * @package Yasca 
 */
class Yasca {

    /**
     * Options parsed from the command line.
     * @access private
     * @var array
     */
    public $options;
    
    /**
     * The results of the scan.
     * @access private
     * @var array
     */
    public $results = array();
    
    /**
     * The target list of files to scan.
     * @access private
     * @var array
     */
    public $target_list = array();
    
    /**
     * The list of available plugin functions.
     * @access private
     * @var array
     */
    public $plugin_list = array();
     
    /**
     * This list contains all attachment references to the general cache
     */
    public $attachment_list = array();
    
    public $plugin_file_list = array();

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
    
    private static $instance = null;
    
    public $cache;
    
    /**
     * Holds the event array for callbacks
     */
    public $event_callback_list = array();
    
    /**
     * Creates a new Yasca scanner object using the options passed in.
     * @param array $options command line options (parsed)
     */
    function Yasca($options = array()) {
        Yasca::$instance =& $this;
        
        // Parse command line arguments if necessary
        $this->options = (count($options) == 0 ? $this->parse_command_line_arguments() : $options);
        
        $this->ignore_list = isset($this->options['ignore-file']) ? $this->parse_ignore_file($this->options['ignore-file']) : array();
        $this->register_callback('post-scan', array(get_class($this), 'remove_ignored_findings'));
        
        $this->cache = new Cache(33554432);     // 32 meg cache
        
        // Scan for target files    
        $this->log_message("Scanning for files...", E_USER_NOTICE);
        
        if (is_file($this->options['dir'])) {       // Allow user to specify a single file
            $this->target_list = array($this->options['dir']);
        } else {
            $this->target_list = $this->dir_recursive($this->options['dir']);
            if (!is_array($this->target_list)) {
                $this->log_message("Invalid target directory specified.", E_USER_ERROR);
            }
        }
        
        // Scan for plugins
        $this->log_message("Scanning for plugins...", E_USER_NOTICE);
        $this->include_plugins($this->options['plugin_dir']);
        if (!is_array($this->plugin_file_list)) {
            $this->log_message("Invalid plugin directory specified.", E_USER_ERROR);
        }
        
    }

    /**
     * Gets the singleton instance of the Yasca object
     */
    public static function &getInstance($options = array()) {
        if (Yasca::$instance == null) {
            $yasca = new Yasca($options);
            Yasca::$instance =& $yasca;
            return $yasca;
        }
        return Yasca::$instance;
    }
        
    /**
     * This function initiaates the scan. After checking various things, it passes
     * execution along to each of the plugins available, on each of the target files
     * available.
     */
    function scan() {
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
            $pinfo = pathinfo($target);
            if (isset($pinfo['extension']) && in_array($pinfo['extension'], $this->options['ignore-ext'])) {
                $total_executions -= count($this->plugin_list);     // compensate for ignored files
                continue;
            }
                
            if (!is_readable($target)) {
                $this->log_message("Unable to read [$target]. File will be ignored.", E_USER_WARNING);
                unset($this->target_list[array_search($target, $this->target_list)]);
                continue;
            }
            
            // This is a slow process - is there a faster alternative?c
            $target_file_contents = file_get_contents($target);
            
            foreach ($this->plugin_list as $plugin) {
                $this->log_message("Initializing plugin [$plugin] on [$target]", E_USER_NOTICE);
                                
                if (!class_exists($plugin)) {
                    $this->log_message("Missing plugin class [$plugin]. Plugin will be ignored.", E_USER_WARNING);
                    unset($this->plugin_list[array_search($plugin, $this->plugin_list)]);
                    $plugin_obj = null;
                    continue;
                }               
                $plugin_obj = @new $plugin($target, $target_file_contents);             
                if (!is_subclass_of($plugin_obj, "Plugin") || !$plugin_obj->initialized) {
                    $this->log_message("Unable to instantiate plugin object of [$plugin] class. Plugin will be ignored.", E_USER_WARNING);
                    unset($this->plugin_list[array_search($plugin, $this->plugin_list)]);
                    $plugin_obj = null;
                    continue;
                }
                $plugin_obj->run();
                $result_list = $plugin_obj->result_list;
    
                $this->log_message("Plugin [$plugin] returned " . count($result_list) . " results. ", E_USER_NOTICE);
    
                if (is_callable($this->progress_callback)) {
                    call_user_func($this->progress_callback, array("progress", ((100 * ++$num_executions) / $total_executions)));
                }

                foreach ($result_list as $result) {
                    array_push($this->results, $result);
                    $result = null;
                    unset($result);
                }
                
                if ($this->options['fixes'] && isset($this->general_cache["proposed_fixes"])) {
                    $fixes = fopen("./fixes.xml", "w");
                    fwrite($fixes, "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>");
                    fwrite($fixes, "<fixes>");
                    fwrite($fixes, $this->general_cache["proposed_fixes"]);
                    fwrite($fixes, "</fixes>");
                    fclose($fixes);
                }
                
                $plugin_obj->destructor();
                $plugin_obj = null;
                $result_list = null;
                
                unset($plugin_obj);
                unset($result_list);
                
                if ($this->options['debug']) {
                    $this->log_message("Memory Usage: [" . memory_get_usage(true) . "] after calling [$plugin] on [$target]", E_USER_WARNING);
                }
            }
            
            $target_file_contents = null;
            unset($target_file_contents);
        }
        
        usort($this->results, array("Yasca", "result_list_comparator"));
    }

    /**
     * Finds and includes all plugins.
     * @param string $plugin_directory directory to look for plugins in (recursively).
     * @return array of functions now available.
     */
    function include_plugins($plugin_dir = "plugins") {
        $plugin_dir_list = explode(",", $plugin_dir);
        $plugin_file_list = array();
        
        // load up all of the plugins
        foreach ($plugin_dir_list as $plugin_item) {
            $plugin_item = trim($plugin_item);
            if (is_file($plugin_item)) {
                array_push($plugin_file_list, $plugin_item);
            } elseif (is_dir($plugin_item)) {
                foreach ($this->dir_recursive($plugin_item) as $plugin_item) {
                    array_push($plugin_file_list, $plugin_item);
                }
            }
        }
        
        // include() each of the plugins
        foreach ($plugin_file_list as $plugin_file) {
            $pinfo = pathinfo($plugin_file);
            $ext = (isset($pinfo['extension']) ? $pinfo['extension'] : "");
            $base = (isset($pinfo['basename']) ? $pinfo['basename'] : "");
            
            if (startsWith($base, "_"))         // ignore all plugin files that start with a '_'
                continue;
            
            if (strtolower($ext) === 'php') {
                foreach ($this->options['plugin_exclude'] as $excluded_plugin) {
                    if ($excluded_plugin != '' &&
                        preg_match('/' . $excluded_plugin . '/i', $plugin_file)) {
                        $this->log_message("Excluding plugin file [$excluded_plugin][$plugin_file].", E_USER_NOTICE);
                        continue 2;
                    }
                }                   
                $class_name = get_class_from_file($plugin_file);
                if ($class_name === false) {
                    $this->log_message("Unable to find class in plugin file [$plugin_file]. Ignoring.", E_USER_WARNING);
                } else {
                    $this->log_message("Including plugin file [$plugin_file].", E_USER_NOTICE);
                    include($plugin_file);
                    array_push($this->plugin_list, $class_name);
                }               
            }
            
            // This holds all files possible for plugins, not just .php files 
            array_push($this->plugin_file_list, $plugin_file);
        }
    }

    /**
     * Recursive directory listing. Returns all files starting
     * at $start_dir.
     * @param string $start_dir starting directory (default=.)
     * @return array of filenames
     */     
     function dir_recursive($start_dir='.') {
        $files = array();
        $start_dir = str_replace("\\", "/", $start_dir);    // canonicalize
        if (is_dir($start_dir)) {
            $fh = opendir($start_dir);
            while (($file = readdir($fh)) !== false) {
                if (strcmp($file, '.')==0 || strcmp($file, '..')==0) continue;
                $filepath = $start_dir . '/' . $file;
                if ( is_dir($filepath) )
                    $files = array_merge($files, Yasca::dir_recursive($filepath));
                else
                    array_push($files, $filepath);
            }
            closedir($fh);
        } else {
            $files = false;
        }
        return $files;
    }
    
    /**
     * Signs a piece of data using a hash. Uses SHA-1 to hash the data.
     * @param string $data string to hash.
     * @param string $password salt used in the calculation.
     * @return hash of the data.
     */
    function calculate_signature($data, $password="3A4B3f39jf203jfALSFJAEFJ30fn2q3lf32cQF3FG") {
        $data = str_replace(array(chr(10), chr(14)), "", $data);
        return sha1($password . $data . $password);
    }
    
    /**
     * Validates if a signature has been tampered with. Uses calculate_signature() to
     * re-calculate the signature.
     * @param string $data string to hash
     * @param string $signature purported signature
     * @param string $password salt used in the calculation.
     * returns true iff the signature matches the expected. 
     */
    function validate_signature($data, $signature, $password="3A4B3f39jf203jfALSFJAEFJ30fn2q3lf32cQF3FG") {
        return ($signature == calculate_signature($data, $password));
    }
    
    /**
     * Validates whether a report content has a valid hash.
     * @param string $data data to check (the report content)
     * @return true iff the signature matches the expected.
     */
    function validate_report($data) {
        if (!$data || strlen($data) == 0) return false;
        $signature = substr($data, strlen($data)-62);
        $matches = array();
        if (!preg_match("/<\!-- SIGNATURE: \[[a-z0-9]+\] -->/", $signature, $matches)) return false;
        $signature = $matches[1];                       // just the hash
        $data = substr($data, 0, strlen($data)-62);     // pull out the signature
        return validate_signature($data, $signature);
    }
    
    /**
     * This is the main error log and event log for the application. Depending on
     * whether the application is running in GUI or console mode, the output is
     * directed appropriately. The severity value is one of the following: E_USER_ERROR,
     * E_USER_WARNING, and E_USER_NOTICE. The function works as following: If --verbose
     * is set, then everything is shown. Otherwise only E_USER_ERROR and E_USER_WARNING.
     * If the silent flag is set, then nothing is shown at all.
     * If the Yasca object is not defined, then this will do a simple print().
     * @param integer $severity severity associated with the message.
     * @param string $message message to write
     * @param boolean $include_timestamp if true, then include a timestamp in the message.
     */
    public static function log_message($message, $severity = E_USER_NOTICE, $include_timestamp = false, $just_print = false) {
        if (!$message || trim($message) == '') return;
        if (!endsWith($message, "\n")) $message .= "\n";
        
        if ($include_timestamp) {
            $message = date('Y-m-d h:i:s ') . $message;
        }
        
        if ($just_print) {
            print $message;
            return;
        }
        
        $yasca =& Yasca::getInstance();     
        
        if (isset($yasca->options['silent']) && $yasca->options['silent']) {
             return;
        } else {                                        // normal case
            $sufficient = false;
            if ( $severity == E_USER_NOTICE && $yasca->options['verbose'] ) {
                $sufficient = true;
            } elseif ( $severity == E_USER_ERROR ) {
                $sufficient = true;
            } elseif ( $severity == E_USER_WARNING ) {
                $sufficient = true;
            } elseif ( $severity == E_ALL && $yasca->options['debug'] ) {
                $sufficient = true;
            }
               
            if ($sufficient) {
                // use a user-defined callback function for the log
                if (isset($yasca->progress_callback) && is_callable($yasca->progress_callback)) {
                    call_user_func($yasca->progress_callback, array("log_message", $message));
                } else {
                    print $message;
                }
            }
            
            if ($severity == E_USER_ERROR) {
                $yasca->log_message("Execution aborted due to error.", E_USER_WARNING); 
                exit(1);
            }
        }

        // Log to a file as well?
        if (isset($yasca->options['log']) && 
            $yasca->options['log'] !== false && 
            is_file($yasca->options['log'])) {
            $d = date('Y-m-d h:i:s ');
            file_put_contents($yobj->options['log'], $d . $message, 'FILE_APPEND');
        }       
    }
        
    /**
     * Parses the command line arguments (argc, argv).
     * @param boolean $parse_arguments actually parse arguments or use the default?
     * @return array of options.
     */
    function parse_command_line_arguments($parse_arguments = true) {
        $opt = array();
        $opt['dir'] = ".";
        $opt['plugin_dir'] = "./plugins";
        $opt['plugin_exclude'] = "";    // will be filled in later and converted to array
        $opt['level'] = 5;
        $opt['verbose'] = false;
        $opt['source_required'] = false;
        $opt['output'] = false;         // will be filled in later when we know the report
        $opt['ignore-ext'] = "exe,zip,jpg,gif,png,pdf,class";
        $opt['report'] = "HTMLGroupReport";
        $opt['silent'] = false;
        $opt['debug'] = false;
        $opt['log'] = false;
        $opt['fixes'] = false;
        $opt['parameter'] = array();    // will be filled in by -d options

        // go through the command line arguments
        for ($i = 1; $i < $_SERVER["argc"]; $i++) {
            switch($_SERVER["argv"][$i]) {
                case "-v":
                case "--version":
                    print("Version " . constant("VERSION") . "\n");
                    print("Copyright (c) 2008 Michael V. Scovetta\n");
                    exit(1);
                    break;
                
                case "--verbose":
                    $opt['verbose'] = true;
                    break;
                    
                case "-h":
                case "--help":
                    Yasca::help();
                    exit(1);
                    break;
                
                case "-d":      /* Pass this parameter to underlying components */
                    parse_str($_SERVER['argv'][++$i], $opt['parameter']);
                    break;
                    
                case "--debug":
                    $opt['debug'] = true;
                    break;
            
                case "--log":
                    $opt['log'] = $_SERVER['argv'][++$i];
                    break;
                            
                case "-i":
                case "--ignore-ext":
                    $opt['ignore-ext'] = $_SERVER['argv'][++$i];
                    break;
                        
                case "--ignore-file":
                    $opt['ignore-file'] = $_SERVER['argv'][++$i];
                    break;
                        
                case "-o":
                case "--output":
                    $opt['output'] = $_SERVER['argv'][++$i];
                    break;
                
                case "-f":
                case "--fixes":
                    $opt['fixes'] = $_SERVER['argv'][++$i];
                    break;
                    
                case "--source-required":
                    $opt['source_required'] = true;
                    break;

                case "-p":
                case "--plugin":
                    $opt['plugin_dir'] = $_SERVER['argv'][++$i];
                    break;
                
                case "-px":
                case "--plugin-exclude":
                    $opt['plugin_exclude'] = $_SERVER['argv'][++$i];
                    break;
                    
                case "-r":
                case "--report":
                    $opt['report'] = $_SERVER['argv'][++$i];
                    break;
                
                case "-s":
                case "--silent":
                    $opt['silent'] = true;
                    break;
                                
                case "-l":
                case "--level";
                    $opt['level'] = $_SERVER['argv'][++$i];
                    break;
                
                default:
                    $opt['dir'] = $_SERVER['argv'][$i];
            }
        }
        
        $opt['ignore-ext'] = str_replace(" ", "", $opt['ignore-ext']);
        $opt['ignore-ext'] = $opt['ignore-ext'] == "0" ? array() : explode(",", $opt['ignore-ext']);

        $extension = Yasca::get_report_extension($opt); 
        $profile_dir = isset($_SERVER['USERPROFILE']) ? $_SERVER['USERPROFILE'] : $_SERVER['HOME'];
        if ($opt['output'] == false) {
            $opt['output'] = $profile_dir . "/Desktop/Yasca/Yasca-Report-" . date('YmdHis') . $extension;
        } elseif (is_dir($opt['output'])) {
            $opt['output'] .= "/Yasca-Report-" . date('YmdHis') . $extension;
        }
        
        $opt['dir'] = realpath($opt['dir']);
    
        $opt['plugin_exclude'] = explode(",", $opt['plugin_exclude']);
        
        if ($parse_arguments == true && $_SERVER['argc'] == 1) {
            Yasca::help();
            exit(1);
        }

        return $opt;
    }
    
    /**
     * Returns the help message (used in console mode.
     * @return text content of the help message (aka usage)
     */
    function help() {
        $help_message = <<<END
Usage: yasca [options] directory
Perform analysis of program source code.

      --debug               additional debugging
  -h, --help                show this help
  -d "QUERYSTRING"          pass the expanded query string to Yasca's components
  -i, --ignore-ext EXT,EXT  ignore these file extensions 
                              (default: exe,zip,jpg,gif,png,pdf,class)
      --ignore-file FILE    ignore findings from the specified xml file
      --source-required     only show findings that have source code available
  -f, --fixes FILE          include fixes, written to FILE (default: not included)
                              (EXPERIMENTAL)
  -l, --level LEVEL         show findings at least LEVEL (1-5) (default: 5)
  -p, --plugin DIR|FILE     load plugins from DIR or just load FILE (default: ./plugins)
  -px PATTERN[,PATTERN...]  exclude plugins matching PATTERN or any of "PATTERN,PATTERN"
                              (multiple patterns must be enclosed in quotes)
  -o, --output FILE         write output to FILE (default: unique file on
                              desktop in Yasca directory)
      --log FILE            write log entries to FILE
  -r, --report REPORT       use REPORT template (default: HTMLGroupReport). Other options
                              include HTMLGroupReport, CSVReport, XMLReport, SQLReport, 
                              and DetailedReport. 
  -s, --silent              do not show any output
  -v, --version             show version information

Examples:
  yasca c:\\source_code
  yasca /opt/dev/source_code
  yasca -px FindBugs,PMD,Antic,JLint /opt/dev/source_code
  yasca -o c:\output.csv --report CSVReport "c:\\foo bar\\quux"
  yasca -d "SQLReport.database=./my.db" -r SQLReport /opt/dev/source_code

END;
        $this->log_message($help_message, E_USER_WARNING);
        exit(1);
    }
    
    /**
     * Compares results to sort them by severity.
     * @param Result $a Result object to compare
     * @param Result $b Result object to compare
     * @return 0, 1, or -1 as per comparator standard
     */ 
    private static function result_list_comparator($a, $b) {
        if (!is_object($a) || !is_object($b)) return 0;
        
        if ($a->severity == $b->severity &&
            $a->category == $b->category) {
            return ($a->filename . $a->line_number < $b->filename . $b->line_number ? 1 : -1);
        } elseif ($a->severity == $b->severity) {
            return ($a->category < $b->category ? 1 : -1);
        } else
            return ($a->severity < $b->severity ? -1 : 1);
    }
    
    /**
     * Finds the actual extension to be used for the report chosen. Includes the period (.).
     * @param array $options program options.
     * @return extension (.html, .xml, .csv, etc.)
     */
    function get_report_extension($options = null) {
        $results = array();
        $report_obj = $this->instantiate_report($results, $options);
        $ext = "." . $report_obj->default_extension;
        unset($report_obj);
        return $ext;
    }
    
    /**
     * Instantiates a new Report object based on the data passed in.
     * @param array $options configuration options (especially 'report')
     * @param array $results place where report results are placed
     */
    function instantiate_report(&$results, $options = null, $default_report='HTMLGroupReport') {
        if (!isset($options) || $options == null) {
            $options =& $this->options;
        }
        
        $report = trim($this->options['report']);
        if (!isset($report) || $report == "") {
            $report = $default_report;
        }
        
        @include_once("lib/$report.php");
        
        if (!class_exists($report)) {
            $this->log_message("Report class [$report] was not found. Defaulting to $default_report.", E_USER_WARNING);
            $report = $default_report;
            $this->options['report'] = $default_report;
        }
        $report_obj = @new $report($this->options, $results);
    
        if (!is_subclass_of($report_obj, "Report")) {
            $this->log_message("Unable to instantiate report object of [$report] class. Defaulting to $default_report.", E_USER_WARNING);
            unset($report_obj);
            $report_obj = new $default_report($this->options, $results);
        }
        return $report_obj;
    }       
    
    /**
     * Attempts to find a target that matches the relative name supplied.
     * @param string $rel_filename filename to search for
     * @return target found, or false if none match.
     */
    function find_target_by_relative_name($rel_filename) {
        $rel_filename = str_replace("\\", "/", $rel_filename);
        foreach ($this->target_list as $target) {
            if (endsWith(str_replace("\\", "/", $target), $rel_filename)) {
                return $target;
            }
        }
        return false;
    }
    
    /**
     * Loads all of the adjustments from resources/adjustments.xml.
     */
    function load_adjustments() {
        $dom = new DOMDocument();
        if (!@$dom->loadXML( file_get_contents("resources/adjustments.xml"))) {
            $this->log_message("Unable to load plugin adjustments. Defaults will be used.", E_USER_WARNING);
            $this->adjusted_severity_list = array();
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
        unset($dom);
    }
    
    /**
     * Retrieves a specific adjustment.
     * @param string $key key use look up
     * @param string $default_value default value, if $key does not exist.
     * @see $this->adjustment_list
     */
    function get_adjustment($key, $default_value) {
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
    function get_adjusted_description($plugin_name, $finding_name, $description) {
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
     */
    function get_adjusted_severity($plugin_name, $finding_name = "", $severity = 5) {
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
    function get_adjusted_alternate_name($plugin_name, $finding_name = "", $default_text = "") {
        if ($plugin_name == "") {
            $this->log_message("No plugin name passed to get_adjusted_alternate_name (finding_name=$finding_name)", E_USER_ERROR);
        }
        if (!isset($this->adjustment_list)) {
            $this->load_adjustments();
        }
        return $this->get_adjustment("$plugin_name.$finding_name.alternate_name.text", $default_text);
    }
    
    /**
     * Adds an attachment to the attachment list. Only allows attachment that are represented in
     * the general cache.
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
     */
    public function register_callback($event, $func) {
        if (!isset($this->event_callback_list[$event])) {
            $this->event_callback_list[$event] = array();
        }
        $event_list = $this->event_callback_list[$event];
        if (!is_array($event_list)) {
            $event_list = array();
        }
            
        if (!in_array($func, $event_list)) {
            array_push($event_list, $func);
        }
        
        $this->event_callback_list[$event] = $event_list;
    }
    
    /**
     * Executes callbacks for a particular event.
     */
    public function execute_callback($event) {
        if (!isset($this->event_callback_list[$event])) return;
        
        $event_list = $this->event_callback_list[$event];
        if (!is_array($event_list)) {
            $this->log_message("Executing callbacks for event [$event] but no callbacks are registered", E_USER_NOTICE);
            return;
        }
        
        foreach ($event_list as $event) {
            $this->log_message("Executing callback [" . implode(", ", $event) . "].", E_ALL);
            @call_user_func($event);    
        }
    }
    
    public function add_fix(&$result, $filename, $line_number, $original, $modified) {
        $result->proposed_fix = "Proposal: " . $modified;
        if (!isset($this->general_cache["proposed_fixes"])) 
            $this->general_cache["proposed_fixes"] = "";
        $this->general_cache["proposed_fixes"] .= "<fix filename=\"$filename\" line_number=\"$line_number\" original=\"" . htmlentities($original) . "\" proposed=\"" . htmlentities($modified) . "\"/>";
    }
    
    private function parse_ignore_file($filename) {
        if (!file_exists($filename) || !is_readable($filename)) return array();
        $dom = new DOMDocument();
        if (!$dom->load($filename)) return array();
        $elts = $dom->getElementsByTagName("ignore");
        $ig_list = array();
        foreach ($elts as $elt) {
            $ig = new StdClass;
            $ig->filename = $elt->getAttribute("filename");
            $ig->line_number = $elt->getAttribute("line_number");
            $ig->category = $elt->getAttribute("category");
            array_push($ig_list, $ig);
        }
        return $ig_list;
    }
    
    private static function remove_ignored_findings() {
        Yasca::log_message("entering remove_ignored_findings()", E_ALL);
        $new_result = array();

        $yasca =& Yasca::getInstance();
        if (!isset($yasca->results) || !is_array($yasca->results)) {
            Yasca::log_message("No results were found.", E_ALL);
        } else {
            foreach ($yasca->results as $result) {
                $b_ignore = false;
                $ignore_list = is_array($yasca->ignore_list) ? $yasca->ignore_list : array();
                
                foreach ($ignore_list as $ignore) {
                    if (is_object($ignore) &&
                        $ignore->filename == str_replace("\\", "/", $result->filename) &&
                        $ignore->line_number == $result->line_number &&
                        $ignore->category == $result->category) {
                        $b_ignore = true;
                        break;
                    }
                }
                if (!$b_ignore) 
                    array_push($new_result, $result);
            }
            $yasca->results = $new_result;
        }
        Yasca::log_message("leaving remove_ignored_findings()", E_ALL);
    }
}
?>

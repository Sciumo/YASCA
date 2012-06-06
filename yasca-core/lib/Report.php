<?php
require_once("lib/Yasca.php");

/**
 * Report Class
 *
 * This (abstract) class is the parent of the specific report renderers. It handles
 * the output stream creation, sorting, and other housekeeping details.  
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
abstract class Report {
   	const default_type = 'HTMLGroupReport';
   	
   	/**
   	 * Returns the default directory path of which to place a report.
   	 * @param boolean $end_with_slashes Whether to end the directory string with a directory separator.
   	 * @return string The default directory path. May not necessarily be fully qualified.
   	 */
    public final static function default_dir($end_with_slash = true){
    	$profile_dir = isset($_SERVER['USERPROFILE']) ? $_SERVER['USERPROFILE'] : $_SERVER['HOME'];
    	return $profile_dir . DIRECTORY_SEPARATOR .
    		"Desktop" . DIRECTORY_SEPARATOR . "Yasca" . 
    	($end_with_slash ? DIRECTORY_SEPARATOR : "");
    }

	 /**
	 * Instantiates a new Report object of type passed in.
	 * @todo More thorough checking for malicious input.
	 * @param string $report_type The name of the type of report to load.
	 * @return Report An instantiated report object of the type $report_type
	 */
	public final static function instantiate_report($report_type){
		$report_type = trim($report_type);
		if (!isset($report_type) || $report_type == "") {
			$report_type = self::default_type;
		}
		//Canonicalize if we've been given a path
		elseif (($cleaned_path = realpath($report_type)) !== false){
			//Protect against path traversal
			$pinfo = pathinfo($cleaned_path);
			if ($pinfo !== false){
				$report_type = $pinfo['filename'];
			}
		}


		@include_once("lib/$report_type.php");
		
		$yasca =& Yasca::getInstance();

		if (!class_exists($report_type) || !is_subclass_of($report_type, "Report")) {
			$yasca->log_message("Report class [$report_type] was not found or is not a subclass of Report." .
				" Defaulting to " . self::default_type . ".", E_USER_WARNING);
			
			$report_type = $yasca->options['report'] = self::default_type;
			@include_once("lib/$report_type.php");
		}
		$report_obj = new $report_type($yasca->options, $yasca->results);
		
		return $report_obj;
	}
	
	
    /**
     * The default extension used for reports of this type.
     * Must be overridden.
     * @var string
     */
    public $default_extension;
    
    /**
     * The default filename for a report of this type.
     * @return string The default filename with the proper extension. No path information is included.
     */
    public final function default_filename(){
    	return "Yasca-Report-" . date('YmdHis') . "." . $this->default_extension;
    }
    
    
    /**
     * Options parsed from the command line.
     * @var array
     */
    protected $options = array();
    
    /**
     * The results of the scan.
     * @var array
     */
    protected $results = array();
    
    /**
     * Include a digital signature in the report file?
     * @var boolean
     * @deprecated
     */
    protected $use_digital_signature = true;

    /**
     * Whether or not the reader of this report should expect a file to be
     * created when execute() is called.
     * @var boolean
     */
    public $uses_file_output = true;
    
    /**
     * @todo Disallow reports from changing the original options and results.
     * @param array $options Array of command line switches
     * @param array $results Array of Result objects.
     */
    public function __construct(array &$options = null, array &$results = null) {
        $this->options =& $options;
        $this->results =& $results;
    }
    
    /**
     * @deprecated Use __construct instead
     */
    public function Report(array &$options = null, array &$results = null){
    	$this->__construct($options, $results);
    }
    
    /**
     * The execute function renders the particular report.
     * @return true iff successful.
     */ 
    public abstract function execute();
    
    /**
     * Tests whether the severity is sufficient to warrant including in the output.
     * @param integer $level level in the (1-5) range.
     * @return boolean true iff the severity is sufficient.
     */
    protected final function is_severity_sufficient($level) {
        return ($this->options['level'] >= $level);
    }
    
    /**
     * Creates an output handle to write the report to. If the requested file is not writeable, the
     * same filename will be attempted to be placed in the temporary directory.
     * @return resource A resource handle to the file
     */
    protected final function &create_output_handle() {
        $handle = 0;
        $output_file =& $this->options["output"];
        $output_file = correct_slashes($output_file, false);
        if (endsWith($output_file,DIRECTORY_SEPARATOR)){
			$output_file .= $this->default_filename();
		} 
		
        if (!file_exists(dirname($output_file))) {
            @mkdir(dirname($output_file),0777, true);
        }
        
        //Basic, but weak, protection against c18n flaws.
        //Also enforces that the file extension matches the report type.
	    $output_file = dirname($output_file) . DIRECTORY_SEPARATOR . 
	    	basename($output_file, "." . $this->default_extension) . 
	    	"." . $this->default_extension;
	    
        if (!$handle = @fopen($output_file, 'w')) {
            $backup_file = tempnam("","sca") . "." . $this->default_extension;
            if (!$handle = @fopen($backup_file, 'w')) {
                Yasca::log_message("Unable to create report file at the provided location nor [$backup_file]. ", E_USER_WARNING);
                    return false;
            }else{
            	Yasca::log_message("Unable to create report file at the provided location." .
            		"Backup file at [$backup_file] used instead. ", E_USER_WARNING);
            	$output_file = $backup_file;
            }
        }
        return $handle;
    }
    


    /**
     * Translated a severity number into a description.
     * @param integer $n severity number (1-5)
     * @return string Description, or 'Unknown' if not in the required range
     */ 
    protected final static function get_severity_description($n) {
        if (!is_numeric($n)) return "Unknown " . ($n == "" ? "" : "($n)");
        
        
        if ($n == 5) { return "Informational"; }
        if ($n == 4) { return "Low"; }
        if ($n == 3) { return "Warning"; }
        if ($n == 2) { return "High"; }
        if ($n == 1) { return "Critical"; }
        return "Unknown ($n)";
    }

    /**
     * Compares results to sort them by severity.
     * @param array $a array of data from Yasca.
     * @param array $b array of data from Yasca.
     * @return 0, 1, or -1 as per comparator requirements.
     * @todo Investigate where this is used; perhaps it can be discarded.
     */ 
    protected static function result_list_comparator($a, $b) {
        if (!is_array($a) || !is_array($b)) return 0;
        if ($a['severity'] == $b['severity'])
            return ($a['filename']. $a['line_number'] < ($b['filename'] . $b['line_number']) ? 1 : -1);
        else
            return ($a['severity'] < $b['severity']) ? -1 : 1;
    }
    
    public final function num_records() {
	return isset($this->results) ? count($this->results) : 0;
    }
}
?>
<?php
/**
 * Report Class
 *
 * This (abstract) class is the parent of the specific report renderers. It handles
 * the output stream creation, sorting, and other housekeeping details.  
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.3
 * @package Yasca
 */
class Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "html";
    
    /**
     * Options parsed from the command line.
     * @access private
     * @var array
     */
    public $options = array();
    
    /**
     * Include a digital signature in the report file?
     * @access private
     * @var boolean
     */
    public $use_digital_signature = true;
    
    /**
     * The results of the scan.
     * @access private
     * @var array
     */
    public $results = array();
    
    public function Report(&$options, &$results) {
        $this->options =& $options;
        if (!is_numeric($this->options['level']) ||
            intval($this->options['level']) < 1 ||
            intval($this->options['level']) > 5) {
            $this->options['level'] = 5;
        }
        $this->results =& $results;
    }
    
    /**
     * The execute function renders the particular report. Since this is
     * is an abstract class, this function should never actually be called,
     * but should be overriden by a subclass.
     * @return true iff successful.
     */ 
    function execute() {
        Yasca::log_message("Report.execute() called, but is abstract. Should have been overridden by a subclass.", E_USER_ERROR);
        return false;
    }
    
    /**
     * Tests whether the severity is sufficient to warrant including in the output.
     * @param integer $level level in the (1-5) range.
     * @return true iff the severity is sufficient.
     */
    function is_severity_sufficient($level) {
        return ($this->options['level'] >= $level);
    }
    
    /**
     * Creates an output handle to write the report to. If the requested file is not writeable, the
     * same filename will be attempted to be placed in the temporary directory.
     * @return a resource handle to the file
     */
    function &create_output_handle() {
        $handle = 0;
        $output_file = $this->options["output"];

        if (!file_exists(dirname($output_file))) {
            @mkdir(dirname($output_file));
        }

	$output_file = dirname($output_file) . "/" . basename($output_file, ".html") . "." . $this->default_extension;
        
        if (!$handle = @fopen($output_file, 'w')) {
            $output_file = rtrim(sys_get_temp_dir(), "\\/") . "/" . basename($output_file);
            if (!$handle = @fopen($output_file, 'w')) {
                Yasca::log_message("Unable to write to the report file [$output_file]. ", E_USER_WARNING);
                    return false;
            }
        }
        $this->options["output"] = $output_file;
        return $handle;
    }

    /**
     * Translated a severity number into a description.
     * @param integer $n severity number (1-5)
     * @return description, or 'Unknown' if not in the required range
     */ 
    function get_severity_description($n) {
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
     */ 
    function result_list_comparator($a, $b) {
        if (!is_array($a) || !is_array($b)) return 0;
        if ($a['severity'] == $b['severity'])
            return ($a['filename']. $a['line_number'] < ($b['filename'] . $b['line_number']) ? 1 : -1);
        else
            return ($a['severity'] < $b['severity']) ? -1 : 1;
    }
}
?>
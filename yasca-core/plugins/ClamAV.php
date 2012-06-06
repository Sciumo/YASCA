<?php

/**
 * The ClamAV Plugin uses ClamAV to discover backdoors, trojans and viruses in the source code.
 *
 * Original plugin by Josh Berry, 04/01/2009
 * Updated by Michael Maass, 06/17/2009 -- slight cleanup, fixed bug where said ClamAV was not installed
 * when it really was.
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_ClamAV extends Plugin {
    public $valid_file_types = array();

    public $is_multi_target = true;

    public $installation_marker = true;		// This is ok because this one is multi-target and it will return if Linux and ClamAV not found

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\clamav\\clamscan.exe",
                               'Linux'   => "clamscan");

    public $arguments = array('Windows' => " --no-summary -d %SA_HOME%resources\\utility\\clamav\\ -ri --detect-pua --max-recursion=5 --max-dir-recursion=30 ",
                              'Linux'   => " --no-summary -ri --detect-pua --max-recursion=5 --max-dir-recursion=30 ");

    protected static $already_executed = false;

    /**
    * Executes ClamAV on the directory
    */
    function execute() {
        if (static::$already_executed) return;
        static::$already_executed = true;
        
        if (!$this->canExecute) return;

        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];      
        $result_list = array();

        $executable = $this->executable[getSystemOS()];
        $arguments = $this->arguments[getSystemOS()];
        $executable = $this->replaceExecutableStrings($executable);
        $arguments = $this->replaceExecutableStrings($arguments);

        if (getSystemOS() == "Windows") {
            if (file_exists($this->replaceExecutableStrings($executable))) {
                $yasca->log_message("Forking external process (ClamAV)...", E_USER_WARNING);
                exec($executable . $arguments . " " . escapeshellarg($dir),  $result_list);
                $yasca->log_message("External process completed...", E_USER_WARNING);
            } else {
                $yasca->log_message("Plugin \"ClamAV\" not installed. Download it at yasca.org.", E_USER_WARNING);
            }
        } else if (getSystemOS() == "Linux") {
            $clamscan_arr = array();
            $clamscan_errorlevel = 0;
            exec("which clamscan", $clamscan_arr, $clamscan_errorlevel);

            if (preg_match("/no clamscan in/", implode(" ", $clamscan_arr)) || $clamscan_errorlevel == 1) {
                $yasca->log_message("ClamAV not detected. Please install and ensure that 'clamscan' is on the system path.", E_USER_WARNING);
                return;
            }

            $yasca->log_message("Forking external process (ClamAV)...", E_USER_WARNING);
            exec( $executable . $arguments . " " . escapeshellarg($dir),  $result_list);
            $yasca->log_message("External process completed...", E_USER_WARNING);
        }

        $yasca->log_message("ClamAV returned: " . implode("\r\n", $result_list), E_ALL);
            
        // Now check each message
        foreach($result_list as $result) {
            if (preg_match("/^((?!clamscan\.exe).)*$/i", $result) && preg_match("/FOUND/i", $result)) {
                $matches = explode(":", $result);
                $filename = $matches[0];
                $message = $matches[1];
            
                $result = new Result();
                $result->line_number = 0;
                $result->filename = $filename;
                $result->plugin_name = "Virus/Trojan Found";
                $result->severity = 1;
                $result->category = "Virus/Trojan Found";
                $result->source = $message;
                $result->is_source_code = false;
                $result->source_context = "";
                $result->description = "A virus, backdoor, trojan or rootkit was found in the source or in a source file";
                array_push($this->result_list, $result);
            }
        }  
    }
}
?>
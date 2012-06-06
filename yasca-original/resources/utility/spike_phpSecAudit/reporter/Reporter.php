<?php 
    /*
    *  $Id: Reporter.php 26734 2005-07-15 01:34:26Z hkodungallur $
    *
    *  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
    *  Licensed under the Open Source License version 2.1
    *  (See http://www.spikesource.com/license.html)
    */
?>
<?php
    
    /** 
     * Abstract base class for any type of report generators
     * writeError function is abstract, which will need to be implemented 
     * by the deriving class. Also implement start and stop functions
     */
    abstract class Reporter
    {
        //Storage for all function input problem
        var $function_input_problem = array();
        var $function_racecheck = array();
        var $function_info = array();
        var $file_name;

        //defaults
        var $racecheck_message_template;
        var $inputproblem_message_template;

        //output vars
        var $fh;
        var $outputFile;

        /* php 4 constructor */
        public function Reporter($outfile) {
            $this->__construct($outfile);
        }

        /* php 5 constructor */
        public function __construct($outfile) {
            $this->outputFile = $outfile;
            $this->_set_defaults();
        }

        //FIXME: make this configurable
        public function _set_defaults() {
            //FIXME: this is the correct message
            $this->racecheck_message_template = "A potential TOCTOU (Time Of Check, Time Of Use) vulnerability exists.
            For more info, visit http://www.secureprogramming.com/?action=view&feature=glossary&glossaryid=256.
            This is the first line where a check has occured.
            The following line(s) contain uses that may match up with this check:
            {racecheck_functions}.";

            //This is the shorter message
            $this->racecheck_message_template = "A potential TOCTOU (Time Of Check, Time Of Use) vulnerability exists.
            For more info, visit http://www.secureprogramming.com/?action=view&feature=glossary&glossaryid=256.";

            $this->inputproblem_message_template = "Argument {argument} to this function call should be checked to ensure that it does not
            come from an untrusted source without first verifying that it contains nothing
            dangerous.";

            $this->function_input_problem = array();
            $this->function_racecheck = array();
            $this->function_info = array();
        }

        public function start() {
            $this->fh = fopen($this->outputFile, "w");
        }

        public function stop() {
            fclose($this->fh);
        }

        public function reset() {
            $this->_set_defaults();
        }

        public function currentlyProcessing($file) {
            $this->file_name = $file;
        }

        public function clean_message($message) {
            $message_array = explode("\n", $message);
            foreach ($message_array as $message_line) {
               $clean_message[] = trim($message_line);
            }
   
            return implode(" ", $clean_message);
        }

        public function log_inputproblem($error_data, $file_name, $line_num, $context) {
            $size = count($this->function_input_problem);
            $severity = "medium";
            $tmp = $error_data[INPUTPROBLEM][SEVERITY];
            $message = preg_replace("/{argument}/", $error_data[INPUTPROBLEM][ARG], $this->inputproblem_message_template);
            $message = $this->clean_message($message);
            if (!empty($tmp))
                $severity = $tmp;
   
            $this->function_input_problem[$size]["severity"] = $severity;
            $this->function_input_problem[$size]["function_name"] = $error_data[NAME];
            $this->function_input_problem[$size]["file_name"] = $file_name;
            $this->function_input_problem[$size]["line_num"] = $line_num;
            $this->function_input_problem[$size]["message"] = $message;
            $this->function_input_problem[$size]["context"] = $context;
        }

        public function log_racecheck($error_data, $file_name, $line_num, $context) {
            $size = count($this->function_racecheck);
            //FIXME: currently uses the default without any processing
            $message = $this->clean_message($this->racecheck_message_template);
            //racecheck is always a medium...
            $severity = "medium";
            $tmp = $error_data[INPUTPROBLEM][SEVERITY];
            if (!empty($tmp))
                $severity = $tmp;
            $this->function_racecheck[$size]["severity"] = $severity;
            $this->function_racecheck[$size]["function_name"] = $error_data[NAME];
            $this->function_racecheck[$size]["file_name"] = $file_name;
            $this->function_racecheck[$size]["line_num"] = $line_num;
            $this->function_racecheck[$size]["message"] = $message;
            $this->function_racecheck[$size]["context"] = $context;
        }


        public function log_info($error_data, $file_name, $line_num, $context) {
            $size = count($this->function_info);
            $message = $this->clean_message($error_data[INFO][DESCRIPTION]);
            $severity = "medium";
            $tmp = $error_data[INFO][SEVERITY];
            if (!empty($tmp))
                $severity = $tmp;

            $this->function_info[$size]["severity"] = $severity;
            $this->function_info[$size]["function_name"] = $error_data[NAME];
            $this->function_info[$size]["file_name"] = $file_name;
            $this->function_info[$size]["line_num"] = $line_num;
            $this->function_info[$size]["message"] = $message;
            $this->function_info[$size]["context"] = $context;
        }
    }
?>

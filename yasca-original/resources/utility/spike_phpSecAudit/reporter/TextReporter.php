<?php
/*
*  $Id: Reporter.php 28215 2006-06-29 11:15:56Z enugroho $
*
*  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
*  Licensed under the Open Software License version 2.1
*  (See http://www.spikesource.com/license.html)
*/

/*
Desc:
This class collects occurances of errors, and collate them into a nice report.

Todo:
It needs to send the report to an instance of a Writer to be written in a certain format.

Born: 06/29/06
*/
if (!defined("PHPSECAUDIT_HOME_DIR")) {
    define("PHPSECAUDIT_HOME_DIR", dirname(__FILE__) . "/..");
}

require_once PHPSECAUDIT_HOME_DIR . "/reporter/Reporter.php";

class TextReporter extends Reporter
{
    //FIXME, These functions should be in writter class

    //Here is the big thing...
    public function report() {
        fwrite($this->fh, "\n\n\nAnalyzing file: ". $this->file_name ." . . . . . .\n\n");

        $header = "The followings are function calls that need input sanitization:\n\n";
        $err_exist1 = $this->print_problems("function_input_problem", "I", $header);

        $header = "These are function calls that need extra cautions:\n\n";
        $err_exist2 = $this->print_problems("function_info", "C", $header);

        $header = "These are function calls that may introduce racecheck problem:\n\n";
        $err_exist3 = $this->print_problems("function_racecheck", "R", $header);
    
        if (!($err_exist1 || $err_exist2 || $err_exist3)) {
            fwrite($this->fh, "There are no errors that I can find in file: ". $this->file_name ."\n\n");
        }
    }
    
    
    //Nicely prints the occurance of problems
    public function print_problems($error_type, $error_group_code, $header) {
        if (!empty($this->$error_type)) {
            fwrite($this->fh, $header);
            
            foreach ($this->$error_type as $problem) {
                $i++;
                fwrite($this->fh, "$error_group_code. $i \n");
                fwrite($this->fh, $problem["file_name"] .": ". $problem["line_num"] .", ". 
                     strtoupper($problem["severity"]) .": ". $problem["function_name"] ."\n");
                fwrite($this->fh, "Context: ". $problem["context"] ."\n");
                fwrite($this->fh, $problem["message"] ."\n\n");
            }
            
            return true;
        }
        else {
            return false;
        }
    }
}

?>

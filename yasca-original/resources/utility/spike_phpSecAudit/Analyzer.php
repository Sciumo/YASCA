<?php
/*
*  $Id: Analyzer.php 28215 2006-06-28 15:27:05Z enugroho $
*
*  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
*  Licensed under the Open Software License version 2.1
*  (See http://www.spikesource.com/license.html)
*/

/*
This class does the following:
1. Traverses the audit directory to get all the php files
2. It Analyzes all the files one by one.
3. It sends it's findings to the an instance of Reporter class for book keeping.

*/

if (!defined("PHPSECAUDIT_HOME_DIR")) {
    define("PHPSECAUDIT_HOME_DIR", dirname(__FILE__));
}

require_once PHPSECAUDIT_HOME_DIR . "/util/TokenUtils.php";
require_once PHPSECAUDIT_HOME_DIR . "/reporter/TextReporter.php";
require_once PHPSECAUDIT_HOME_DIR . "/reporter/XmlReporter.php";
require_once PHPSECAUDIT_HOME_DIR . "/xml_utils.php";
require_once PHPSECAUDIT_HOME_DIR . "/util/Utility.php";

class Analyzer {
    //Worker objects
    var $line_tokenizer;
    var $reporter;
    var $_stats;
    var $_xsl;

    var $outformat;
    var $errorsflag;
    var $errors;

    var $file_name;
    var $prvs_token;
    var $token;
    var $line_number;
    var $prvs_line;
    var $line_array = array();
    var $xml_db_file;
    var $error_types;
    var $validExtensions;
    
    //FIXME: naming, not good....
    var $item_array = array();
    var $item_names = array();
    
    /* php 4 constructor */
    function Analyzer() {
        echo "HI!!\n";
        $this->__construct();
    }
    
    /* php 5 constructor */
    function __construct() {
        $this->validExtensions[] = "php";
        $this->_load_error_types();
        $this->line_tokenizer = new TokenUtils();
    }
    
    // {{{ function processFiles

    /**
     * driver function that call processFile repeatedly for each php
     * file that is encountered
     *
     * @return nothing
     */
    function process_files($src, $excludes = array(), $outformat, $outdir) {
        $this->outformat = $outformat;
        global $util;
        $files = $util->getAllPhpFiles($src, $excludes);

        if ($outformat == "rate") {
            $errorTotal = 0;
            $lineTotal = 0;
            foreach ($files as $file) {
                if (is_array($file)) {
                    continue;
                }
                $this->_process_file($file);
                $errorTotal += $this->errors;
                $lineTotal += count($this->line_array);
            }
            echo $errorTotal . "\n";
            echo $lineTotal . "\n";
        } else {
            // if output directory does exist, let's start fresh by deleting
            // its contents; otherwise, create it
            if (file_exists($outdir)) {
                $util->wipeDir($outdir);
            } else {
                $util->makeDirRecursive($outdir);
            }

            if ($outformat == "text") {
                $this->reporter = new TextReporter($outdir . "/output.txt");
                $this->reporter->start();
                foreach ($files as $file) {
                    if (is_array($file)) {
                        continue;
                    }
                    $this->reporter->reset();
                    $this->_process_file($file);
                    $this->reporter->currentlyProcessing($this->file_name);
                    $this->reporter->report();
                }
                $this->reporter->stop();
            } else {
                // copy the css and images
                $util->copyr(PHPSECAUDIT_HOME_DIR . "/html/css", $outdir . "/css");
                $util->copyr(PHPSECAUDIT_HOME_DIR . "/html/images", $outdir . "/images");
 
                // setup html translation for the isolated reports
                $this->_xsl = new XSLTProcessor();
                $outFile = $outdir . "/" . "temp_perfile.xml";
                $xslFile = PHPSECAUDIT_HOME_DIR . "/html/xsl/perfile.xsl";
                $this->_xsl->importStyleSheet(DOMDocument::load($xslFile));

                // setup reporter for compiled html list/summary
                $this->_stats = new XmlReporter($outdir . "/" . "temp_index.xml", "", $src);
                $this->_stats->start();

                foreach ($files as $file) {
                    if (is_array($file)) {
                        continue;
                    }

                    // for output purposes, the entire path is lengthy for dir creation
                    $relfile = str_replace($src . "/", "", $file);
                    // any need for php output files? - already presumed existence as server module
                    $htmlFile = $outdir . "/" . $relfile . ".html";

                    // a directory tree, similar to that of the original source, will be created
                    // this is for easier navigation
                    $curDir = dirname($htmlFile);
                    if (!file_exists($curDir)) {
                        $util->makeDirRecursive($curDir);
                    }

                    // get relative path to home directory by joining "../" multiple times
                    // this is so that files deep in recreated dir tree can access css
                    $temp = str_replace("/", "", $relfile);     //num dirs traversed
                    $count = strlen($relfile) - strlen($temp);
                    $relpath = str_repeat("../", $count);

                    // setup reporter for individual isolated file reports
                    // provide where to output, path to css/images, and simpler path for display
                    $this->reporter = new XmlReporter($outFile, $relpath, $relfile);
                    $this->reporter->start();

                    // begin <file> info in xml file
                    $this->reporter->currentlyProcessing($file, $relfile);
                    $this->_stats->currentlyProcessing($file, $relfile);

                    // the code involving 'errorsflag' prevents creation of isolated
                    // report files in which there are no errors to report
                    $this->errorsflag = false;
                    $this->_process_file($file);        // produce error messages in xml file
                    if (!$this->errorsflag) {
                        continue;
                    }

                    // halt adding info and transform the xml file into isolated html report
                    $this->reporter->stop();
                    $this->_transformXSL($outFile, $htmlFile);
                }

                // halt adding info to overall list
                $this->_stats->stop();
                // setup html translation for the compiled list
                $xslFile = PHPSECAUDIT_HOME_DIR . "/html/xsl/index.xsl";
                $this->_xsl->importStyleSheet(DOMDocument::load($xslFile));
                $this->_transformXSL($outdir . "/" . "temp_index.xml", $outdir . "/" . "index.html");
            }
        }
    }

    /**
     * simple function that translates a given xml file into an html
     * file that is saved to the given location
     *
     * @param $xmlfile where to read data from
     * @param $htmlfile where to output translation to
     * @return nothing
     * @access private
     */
    function _transformXSL($xmlFile, $htmlFile)
    {
        $hfh = fopen($htmlFile, "w");
        fwrite($hfh, $this->_xsl->transformToXML(DOMDocument::load($xmlFile)));
        fclose($hfh);
    }

    function _process_file($file) {
        $this->file_name = $file;
        $this->line_array = file($this->file_name);
        $this->line_number = 0;
        $this->errors = 0;
        $this->line_tokenizer->reset();
        $this->line_tokenizer->tokenize($this->file_name);
        $this->_move_token();
        while ($this->token) {
            if (is_array($this->token)) { 
                list ($tok, $text) = $this->token;
                $this->_process_token($text, $tok);
            }
            
            /*
            //FIXME: We are not testing for these yet
            else if (is_string($this->token)) { 
                $text = $this->token; 
                $this->_processString($text);
            }
            */

            $this->_move_token();
        }
    }



    /** 
     * This is the xml - db section
     * may need a new abstraction
     */
    function set_xml_db($file) {
        $this->xml_db_file = $file;    
        $xml_str = file_get_contents($this->xml_db_file);
        $xml_array = MyXMLtoArray($xml_str);
        $this->item_array = $xml_array[VULNDB][VULNERABILITY];
        
        foreach ($this->item_array as $index => $item) {
            $this->item_names[$item[NAME]] = $index;
        }
    }
    

    /**
     * FIXME - WARNING: HARDCODED STUFF
     * ALL OF THESE HAVE TO BE REPLACED
     */
    function _load_error_types() {
        $this->error_types[] = "INPUTPROBLEM";
        $this->error_types[] = "RACECHECK";
        $this->error_types[] = "INFO";

        //Not implemented yet
        //$this->error_types[] = "INPUT";
        //$this->error_types[] = "RACEUSE";
    }

    
    // {{{ function _move_token

    function _move_token() 
    {
        $this->prvs_token = $this->token;
        $this->prvs_line = $this->line_number;
        $this->token = $this->line_tokenizer->getNextToken($this->line_number);

    }

    // }}}
    
    // {{{function _process_token

    /** 
     * processes a token that is not a string, that is
     * a token that is a array of a token id (key) and
     * a token text (value)
     * 
     * @param $text the text of the token
     * @param $tok token id
     * @return nothing
     */
    function _process_token($text, $tok) {
        //FIXME: Do we have other cases?
        switch($tok){
            case T_STRING:
                $this->process_function($text);
                break;
        }
    }   

    
    /**
     * Checks if there is any security issue related to $function_name
     * Prints appropriate messages.
     *
     */
    function process_function($function_name) {
        if (is_int($this->item_names[$function_name])) {
            $this->errorsflag = true;
            $index = $this->item_names[$function_name];
            $context = trim($this->line_array[$this->prvs_line]);
 
            if ($this->is_inputproblem($function_name)) {
                $this->reporter->log_inputproblem($this->item_array[$index], 
                                 $this->file_name, $this->prvs_line, $context);
                if ($this->outformat == "html") {
                     $this->_stats->log_inputproblem($this->item_array[$index],
                                    $this->file_name, $this->prvs_line, $context);
                }
            }
            
            if ($this->is_racecheck($function_name)) {
                $this->reporter->log_racecheck($this->item_array[$index],
                                 $this->file_name, $this->prvs_line, $context);
                if ($this->outformat == "html") {
                     $this->_stats->log_racecheck($this->item_array[$index],
                                    $this->file_name, $this->prvs_line, $context);
                }

            }

            if ($this->is_info($function_name)) {
                $this->reporter->log_info($this->item_array[$index],
                                 $this->file_name, $this->prvs_line, $context);
                if ($this->outformat == "html") {
                     $this->_stats->log_info($this->item_array[$index],
                                    $this->file_name, $this->prvs_line, $context);
                }
            }
        }
    }
    
    
    function is_inputproblem($function) {
        $key = $this->item_names[$function];
        //echo "$key  $function \n";
        if (empty($this->item_array[$key][INPUTPROBLEM]))
            return false;
        
        return true; 
    }
    
    function is_racecheck($function) {
        $key = $this->item_names[$function];
        if ($this->item_array[$key][RACECHECK] < 1)
            return false;
        
        return true; 
    }
    
    function is_info($function) {
        $key = $this->item_names[$function];
        //echo "$key  $function \n";
        if (empty($this->item_array[$key][INFO]))
            return false;
        
        return true; 
    }
    
  

    /** 
     * Processes a string token 
     * 
     * @param $text the token string
     * @return nothing
     */
    function _processString($text) {
        //echo "Processing string $text \n";
    }
    
}

?>

<?php 
    /*
    *  $Id: XmlFormatReporter.php 26740 2005-07-15 01:37:10Z hkodungallur $
    *
    *  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
    *  Licensed under the Open Source License version 2.1
    *  (See http://www.spikesource.com/license.html)
    */
?>
<?php
     if (!defined("PHPSECAUDIT_HOME_DIR")) {
        define("PHPSECAUDIT_HOME_DIR", dirname(__FILE__) . "/..");
    }

    require_once PHPSECAUDIT_HOME_DIR . "/reporter/Reporter.php";

    class XmlReporter extends Reporter
    {
        private $document = false;
        private $root = false;
        private $currentElement = false;

        private $rootDir = false;         // path to root of output directory
        private $home = false;            // first overall xml element 
        private $path = false;            // file location for display purposes
        private $lineTotal = 0;           // sum lines into 'home' element for xsl ease

        /**
         * Constructor; calls parent's constructor 
         */
        public function __construct($outfile, $reldir, $path) 
        {
            parent::__construct($outfile);
            $this->rootDir = $reldir;
            $this->path = $path;
        }

        /** 
         * @see Reporter::start
         * 
         */
        public function start() 
        {
            $this->_initXml();
        }

        /** 
         * @see Reporter::start
         * add the last element to the tree and save the DOM tree to the 
         * xml file
         * 
         */
        public function stop() 
        {
            $this->_endCurrentElement();
            $this->home->setAttribute("lineTotal", $this->lineTotal);
            $this->document->save($this->outputFile);
        }

        /** 
         * @see Reporter::currentlyProcessing
         * add the previous element to the tree and start a new elemtn
         * for the new file
         * 
         */
        public function currentlyProcessing($phpFile, $name=false) 
        {
            parent::currentlyProcessing($phpFile);
            $this->_endCurrentElement();
            $this->_startNewElement($phpFile, $name);
        }

        /** 
         * @see Reporter::writeError 
         * creates a <error> element for the current doc element
         * 
         */
        public function writeError($line, $message, $context, $font) 
        {
            $e = $this->document->createElement("error");
            $e->setAttribute("line", $line);
            $e->setAttribute("message", $message);
            $e->setAttribute("context", $context);
            $e->setAttribute("font", $font);
            $this->currentElement->appendChild($e);
        }

        private function _initXml() 
        {
            $this->document = new DomDocument("1.0");
            $this->root = $this->document->createElement('phpsecaudit');
            $this->document->appendChild($this->root);
            
            // add the path to css+images, as well as path for display purposes
            $this->home = $this->document->createElement("home");
            $this->currentElement = $this->home;
            $this->currentElement->setAttribute("src", $this->rootDir);
            $this->currentElement->setAttribute("path", $this->path);
            $this->_endCurrentElement();
        }

        private function _startNewElement($f, $name=false)
        {
            $this->currentElement = $this->document->createElement("file");
            //add all file info desired for stats here

            //if fake name provided display it, else lengthy filename
            if ($name) {                 
                $this->currentElement->setAttribute("name", $name);
            } else { 
                $this->currentElement->setAttribute("name", $f);
            }

            $lines = count(file($f));
            $this->currentElement->setAttribute("lines", $lines);
            $this->lineTotal += $lines;
        }

        private function _endCurrentElement()
        {
            if ($this->currentElement) {
                $this->root->appendChild($this->currentElement);
            }
        }
   
        public function log_inputproblem($error_data, $file_name, $line_num, $context) {
            parent::log_inputproblem($error_data, $file_name, $line_num, $context);
            $more = preg_replace("/{argument}/", $error_data[INPUTPROBLEM][ARG], $this->inputproblem_message_template);
            $more = $this->clean_message($more);
            $simple = "I) Function call needs input sanitization - ";
            $message = $simple . $more;
            $font = count($this->function_input_problem);
            $severity = $this->function_input_problem[$font - 1]["severity"];
            $font = $this->translate($severity);
            $this->writeError($line_num, $message, $context, $font);
        }

        public function log_racecheck($error_data, $file_name, $line_num, $context) {
            parent::log_racecheck($error_data, $file_name, $line_num, $context);
            $more = $this->clean_message($this->racecheck_message_template);
            $simple = "R) Function call may introduce racecheck problems - ";
            $message = $simple . $more;
            $font = count($this->function_racecheck);
            $severity = $this->function_racecheck[$font - 1]["severity"];
            $font = $this->translate($severity);
            $this->writeError($line_num, $message, $context, $font);
        }

        public function log_info($error_data, $file_name, $line_num, $context) {
            parent::log_info($error_data, $file_name, $line_num, $context);
            $more = $this->clean_message($error_data[INFO][DESCRIPTION]);
            $simple = "C) Function call needs extra cautions - ";
            $message = $simple . $more;
            $font = count($this->function_info);
            $severity = $this->function_info[$font - 1]["severity"];
            $font = $this->translate($severity);
            $this->writeError($line_num, $message, $context, $font);
        }
 
        private function translate($severity) {
            if ($severity == "High") {
                return "font-size: 10px; font-weight: bold; color: red;";
            } elseif ($severity == "medium") {
                return "font-size: 9px; font-weight: bold; color: black;";
            } elseif ($severity == "low") {
                return "font-size: 9px; font-weight: bold; color: green;";
            }
            return "";
        }
    }
?>

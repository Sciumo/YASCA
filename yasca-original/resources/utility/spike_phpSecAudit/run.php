<?php
/*
*  $Id: run.php 28215 2006-06-28 13:35:02 Z enugroho $
*
*  Copyright(c) 2004-2005, SpikeSource Inc. All Rights Reserved.
*  Licensed under the Open Software License version 2.1
*  (See http://www.spikesource.com/license.html)
*/

/*
* A simple driver file...
*/
    define("PHPSECAUDIT_HOME_DIR", dirname(__FILE__));

    function usage()
    {
        echo "Usage: " . $_SERVER['argv'][0] . " <options>\n";
        echo "\n";
        echo "    Options: \n";
        echo "       --src          Root of the source directory tree or a file.\n";
        echo "       --exclude      [Optional] A directory or file that needs to be excluded.\n";
        echo "       --format       [Optional] Output format (html/text). Defaults to 'html'.\n";
        echo "       --outdir       [Optional] Report Directory. Defaults to './style-report'.\n";
        echo "       --help         Display this usage information.\n";
        exit;
    }


    // default values
    $OPTION['src'] = false;
    $OPTION['exclude'] = array();
    $OPTION['format'] = "html";
    $OPTION['outdir'] = PHPSECAUDIT_HOME_DIR . "/output";

    // loop through user input
    for ($i = 1; $i < $_SERVER["argc"]; $i++) {
        switch ($_SERVER["argv"][$i]) {
        case "--src":
            $OPTION['src'] = $_SERVER['argv'][++$i];
            break;

        case "--outdir":
            $OPTION['outdir'] = $_SERVER['argv'][++$i];
            break;

        case "--exclude":
            $OPTION['exclude'][] = $_SERVER['argv'][++$i];
            break;

        case "--format":
            $OPTION['format'] = $_SERVER['argv'][++$i];
            break;
         
        case "--help":
            usage();
            break;
        }
    }

    //validity checks

    // check that source directory is specified and is valid
    if ($OPTION['src'] == false) {
        echo "\nPlease specify a source directory/file using --src option.\n\n";
        usage();
    }

    if (($OPTION['format'] != "html") && ($OPTION['format'] != "text")
                                      && ($OPTION['format'] != "rate")) {
        echo "\nUnknown format.\n\n";
        usage();
    }

    require_once PHPSECAUDIT_HOME_DIR . "/Analyzer.php";

    $xml_db_file = PHPSECAUDIT_HOME_DIR . "/vuln_db.xml";

    $analyzer = new Analyzer();
    $analyzer->set_xml_db($xml_db_file);
    $analyzer->process_files($OPTION['src'], $OPTION['exclude'], $OPTION['format'], $OPTION['outdir']);

    echo "Reporting Completed. Please check the results.\n\n";
?>

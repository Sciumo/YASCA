<?php
/**
 * Yasca Engine, Yasca Static Analysis Tool
 * 
 * This pacakge implements a simple engine for static analysis
 * of source code files. 
 * @author Michael V. Scovetta <scovetta@sourceforge.net>
 * @version 1.3
 * @package Yasca
 */

chdir(dirname($_SERVER["argv"][0]));        // get back to the current directory

// Use this to show ALL possible errors
error_reporting(E_ALL | E_PARSE);
set_error_handler("custom_error_handler");

include_once("lib/Yasca.php");
include_once("lib/common.php");
include_once("lib/Report.php");


 /**
  * Main entry point for the Yasca engine.
  */
function main() {
    Yasca::log_message("Yasca " . constant("VERSION") . " - http://www.yasca.org/ - Michael V. Scovetta\r\n\r\n", E_USER_NOTICE, false, true);
    Yasca::log_message("Initializing components...", E_USER_WARNING);

    $yasca =& Yasca::getInstance(); 

    if ($yasca->options['debug']) profile("init");
    $yasca->execute_callback("pre-scan");
    Yasca::log_message("Starting scan. This may take a few minutes to complete...", E_USER_WARNING);
    $yasca->scan();
    
    Yasca::log_message("Executing post-scan callback functions.", E_ALL);
    $yasca->execute_callback("post-scan");

    Yasca::log_message("Executing pre-report callback functions.", E_ALL);    
    $yasca->execute_callback("pre-report");
    Yasca::log_message("Creating report...", E_USER_WARNING);
    
    $report = $yasca->instantiate_report($yasca->results);
    $report->execute();
    $yasca->execute_callback("post-report");
    
    Yasca::log_message("Results have been written to " . correct_slashes($yasca->options["output"]), E_USER_WARNING);
    
    if ($yasca->options['debug']) print_r(profile("get"));
}


/**
 * Function profiler for PHP.
 * @param string $cmd either 'init' or 'get'
 * @return array of profiling information, if 'get' was passed.
 */
function profile($cmd = false) {
    static $log, $last_time, $total;
    list($usec, $sec) = explode(" ", microtime());
    $now = (float) $usec + (float) $sec;
    if($cmd) {
        if($cmd == 'get') {
            unregister_tick_function('__profile__');
            foreach($log as $function => $time) {
                if($function != '__profile__') {
                        $by_function[$function] = round($time / $total * 100, 2);
                }
            }
            arsort($by_function);
            return $by_function;
        }
        else if($cmd == 'init') {
            $last_time = $now;
            register_tick_function('profile');      // Register the tick function
            declare(ticks=1);                       // Start at # ticks = 1         
            return;
        }
    }
    $delta = $now - $last_time;
    $last_time = $now;
    $trace = debug_backtrace();
    $caller = $trace[1]['function'];
    @$log[$caller] += $delta;
    $total += $delta;
}

function custom_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
    if (error_reporting() == 0) return;
    print "[" . error2string($errno) . "] [ $errfile:$errline ] $errstr\n";
}

/**
 * Converts an error value to a string.
 * Thanks to Chris at http://us.php.net/error_reporting for this function.
 */
function error2string($value) {
    $level_names = array(
        E_ERROR => 'E_ERROR', 
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE', 
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR', 
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR', 
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR', 
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE' 
    );
    
    if(defined('E_STRICT')) 
        $level_names[E_STRICT]='E_STRICT';

    $levels=array();

    if( ($value & E_ALL) == E_ALL) {
        $levels[] = 'E_ALL';
        $value &= ~E_ALL;
    }
    foreach ($level_names as $level => $name)
        if ( ($value & $level) == $level)
            $levels[] = $name;

    return implode(' | ', $levels);
}


/* Start the main function */
main();
?>

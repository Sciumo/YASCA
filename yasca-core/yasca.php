<?php
/**
 * Yasca Engine, Yasca Static Analysis Tool
 * 
 * This package implements a simple engine for static analysis
 * of source code files. 
 * @author Michael V. Scovetta <scovetta@sourceforge.net>
 * @version 2.1
 * @license see doc/LICENSE
 * @package Yasca
 */

chdir(dirname($_SERVER["argv"][0]));        // get back to the current directory

//Configure error reporting levels and sinks in the ini file instead of here.

require_once("lib/Yasca.php");
require_once("lib/common.php");
require_once("lib/Report.php");


/**
 * Main entry point for the Yasca engine.
 */
function main() {
	$options = Yasca::parse_command_line_arguments();
	if (!$options['silent']){
	    Yasca::log_message("Yasca " . constant("VERSION") . " - http://www.yasca.org/ - Michael V. Scovetta", E_USER_NOTICE, false, true);
	    Yasca::log_message(Yasca::getAdvertisementText("TEXT") . "\n\n", E_USER_WARNING);
	
	    Yasca::log_message("Initializing components...", E_USER_WARNING);
	}
	
   	$yasca =& Yasca::getInstance($options); 
    $yasca->log_message("Using Static Analyzers located at [{$yasca->options['sa_home']}]", E_USER_WARNING);

    if ($yasca->options['debug']) profile("init");
    $yasca->execute_callback("pre-scan");
    $yasca->log_message("Starting scan. This may take a few minutes to complete...", E_USER_WARNING);
    $yasca->scan();
    
    $yasca->log_message("Executing post-scan callback functions.", E_ALL);
    $yasca->execute_callback("post-scan");

    $yasca->log_message("Executing pre-report callback functions.", E_ALL);    
    $yasca->execute_callback("pre-report");
    
    $yasca->log_message("Creating report...", E_USER_WARNING);
    $report = $yasca->instantiate_report();
    $report->execute();
    
    $yasca->execute_callback("post-report");
    
    if ($report->uses_file_output) 
        $yasca->log_message("Results have been written to " . correct_slashes($yasca->options["output"]), E_USER_WARNING);
    
    if ($yasca->options['debug']){ 
    	$yasca->log_message("\nProfiling Information:", E_USER_WARNING);
        $yasca->log_message("  Class, Function, Seconds", E_USER_WARNING);
	    $profile_info = profile("get");
	    
	    arsort($profile_info);
	    
        foreach($profile_info as $key => $value){
            if (startsWith($key, ",")) $key = "(None) " . $key;
            $value = round($value, 4);
			print("  $key, $value\n");
		}
	}
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
        	//@todo Are these underscores an error?
            unregister_tick_function('__profile__');
            foreach($log as $function => $time) {
                if($function != '__profile__') {
                        $by_function[$function] = $time;
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
    $caller = @$trace[1]['class'] . ", " . @$trace[1]['function'];
    @$log[$caller] += $delta;
    $total += $delta;
}


/* Start the main function */
main();
?>

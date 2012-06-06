<?php

include_once("lib/Report.php");

/**
 * PHPObjectReport Class
 *
 * This class renders scan results as a serialized PHP object..
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
class PHPObjectReport extends Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "data";
    
    /**
     * Executes an PHPObjectReport, with output going to $options['output']
     */ 
    function execute() {
        if (!$handle = $this->create_output_handle()) return;

        fwrite($handle, serialize($this->results));
        fclose($handle);
    }
}



?>

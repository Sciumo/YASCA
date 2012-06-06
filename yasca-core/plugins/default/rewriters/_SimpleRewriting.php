<?php

/**
 * This class looks for weak authentication values, where *.username = *.password.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_replaceAsWithBs extends Plugin {
    public $valid_file_types = array();

    function execute() {
        $output = array();
        for ($i=0; $i<count($this->file_contents); $i++) {
            if ( preg_match('/a/i', $this->file_contents[$i]) ) {
                $output[$i] = preg_replace('/a/', 'b', $this->file_contents[$i]);
            }
        }
        file_put_contents("output", $output, FILE_APPEND);
    }
}
/*
Write output in the format:
<finding id="1" path="./foo/something.jsp">

</finding>
*/
?>

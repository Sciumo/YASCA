<?php

/**
 * This class looks finds all calls to request.getParameter, extracts
 * all of the variable names, and puts them in a spreadsheet.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_StrutsValidator extends Plugin {
    public $valid_file_types = array("jsp", "java");
    
    function execute() {
        $yasca =& Yasca::getInstance();
        
        $parameter_list = isset($yasca->general_cache['Plugin_StrutsValidator.parameter_list']) ? 
                                $yasca->general_cache['Plugin_StrutsValidator.parameter_list'] : 
                                array();
        $is_changed = false;
        
        for ($i=0; $i<count($this->file_contents); $i++) {
            if (preg_match('/req(uest)?\.getParameter\(\s*"([^"]*)"/', $this->file_contents[$i], $matches)) {
                $parameter_name = $matches[2];
                $key = $this->filename . "~~~" . $parameter_name;
                if (array_search($parameter_list, $key) === false) {
                    array_push($parameter_list, $key);
                    $is_changed = true;
                }
            }
        }
        if (!$is_changed) return;
        
        $outfile = fopen("validator.csv", "w");
        foreach ($parameter_list as $parameter) {
            $parameter_array = explode("~~~", $parameter);
            $filename = $parameter_array[0];
            $parameter = $parameter_array[1];
            fprintf($outfile, "<tr>");
            fprintf($outfile, "<td>");
            fputcsv($outfile, array($filename, $parameter));
        }       
        fclose($outfile);
        
        $yasca->general_cache['Plugin_StrutsValidator.parameter_list'] = $parameter_list;
        $parameter_list = null;
    }
    
    function html_header() {
        $version = constant("VERSION");
        $yasca =& Yasca::getInstance();
        $target_list = str_replace("/", "\\", implode("<br>", $yasca->target_list));
        $stylesheet_content = file_get_contents("resources/style.css");
        return <<<END
<html>
    <head>
        <title>Yasca $version - Validation Wizard</title>
        <style type="text/css">
            $stylesheet_content
        </style>
    </head>
    
    <body>
END;
    }

}
?>
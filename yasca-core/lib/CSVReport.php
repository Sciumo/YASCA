<?php
require_once("lib/common.php");
require_once("lib/Report.php");
require_once("lib/Result.php");

/**
 * CSVReport Class
 *
 * This class renders scan results as CSV.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */

class CSVReport extends Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "csv";

    /**
     * Executes a CSVReport, with output going to $options['output']
     */ 
    public function execute() {
        if (!$handle = $this->create_output_handle()) return;
        
        fwrite($handle, $this->get_preamble());
        
        $num_results_written = 0;
        foreach ($this->results as $result) {
            if (!$this->is_severity_sufficient($result->severity))
                continue;
            $filename = $result->filename;
            $pinfo = pathinfo($filename);
            $ext = $pinfo['extension'];
            if (isset($result->custom['translation'])) {
                $t = $result->custom['translation'];
                $filename = $t[basename($filename, ".$ext")];
            }

            $row_id = sprintf("%03d", ++$num_results_written);
            $category_link = $result->category_link;
            $category = $result->category;
            $plugin_name = $result->plugin_name;
            $source = trim($result->source);
            $severity_description = $this->get_severity_description($result->severity);
            $filename_base = basename($filename);
            $line_number = $result->line_number;
            $line_number_field = $result->line_number > 0 ? ":" . $result->line_number : "";
            $category_link_field = "<a href=\"$category_link\" target=\"_blank\">$category</a>";
            if ($category_link == "") $category_link_field = $category;
            
            fwrite($handle,
                    "\"$row_id\"," . 
                    "\"$category\"," .
                    "\"$plugin_name\"," .
                    "\"$severity_description\"," .
                    "\"$filename_base$line_number_field\"," .
                    "\"$filename\"," .
                    "\"$source\"\n");                   
        }
        
        fwrite($handle, $this->get_postamble());        
        fclose($handle);
    }
    
    protected function get_preamble() {
        return '"#","Category","Plugin Name","Severity","Location","Full Location","Message"' . "\n";
    }
        
    protected function get_postamble() {
        return "";
    }   
}



?>

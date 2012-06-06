<?php
//TODO FINISH THIS
include_once("lib/Report.php");

/**
 * DetailedReport Class
 *
 * This class renders scan results in a very detailed, one-page-per-issue format.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.0
 * @package Yasca
 */
class DetailedReport extends Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "html";
    
    /**
     * Executes a DetailedReport, with output going to $options['output']
     * The report will have: [header] [issue found] [code snippet] [description] [rule info]
     */ 
    function execute() {
        if (!$handle = $this->create_output_handle()) return;
        
        $description_cache = array();
        
        fwrite($handle, $this->get_preamble());
        
        $num_results_written = 0;
        foreach ($this->results as $result) {
            if (!$this->is_severity_sufficient($result->severity))
                continue;
            $filename = $result->filename;
            $pinfo = pathinfo($filename);
            $ext = $pinfo['extension'];
            if (isset($result->custom['translation'])) {
                $t =& $result->custom['translation'];
                $filename = $t[basename($filename, ".$ext")];
            }
            $filename = str_replace("\\", "/", $filename);

            $source_context = "";
            if (is_array($result->source_context)) {
                foreach ($result->source_context as $context) {
                    $context = preg_replace('/[\r\n]/', "", $context);
                    $context = trim($context);
                    $source_context .= htmlentities($context) . "<br/>";
                }
            }

            $row_id = sprintf("%03d", ++$num_results_written);
            $category_link = $result->category_link;
            $category = $result->category;
            $plugin_name = $result->plugin_name;
            $description = ($result->description != "" ? $result->description : "<i>No description available.</i>");
            $source_code_class = ($result->is_source_code ? "code" : "");
            $source = $result->source;
            $severity_description = $this->get_severity_description($result->severity);
            $filename_base = basename($filename);
            $line_number = $result->line_number;
            $line_number_field = $result->line_number > 0 ? ":" . $result->line_number : "";
            $category_link_field = "<a href=\"$category_link\" target=\"_blank\">$category</a>";
            if ($category_link == "") $category_link_field = $category;
            
            /*
            if (preg_match('/owasp/', $category_link)) {
                if (!isset($description_cache[$category_link])) {
                    $description = get_owasp_vulnerability_content($category_link);
                    $description_cache[$category_link] = $description;
            }
            }
            */
            
            $page_content = <<<END
    <table class="header">
        <tr>
            <td colspan="2" class="top_header">Yasca Finding #$row_id</td>
        </tr>
        <tr>
            <td class="header_field">Category:</td>
            <td class="header_content">$category</td></tr>
        <tr>
            <td class="header_field">File:</td>
            <td class="header_content">$filename $line_number_field</td></tr>
        <tr>
            <td class="header_field">Severity:</td>
            <td class="header_content">$severity_description</td></tr>
        <tr>
            <td class="header_field">Plugin:</td>
            <td class="header_content">$plugin_name</td></tr>
    </table>
    
    <table class="section">
        <tr><td class="section_field">Message:</td></tr>
        <tr><td class="section_content $source_code_class">$source</td></tr></table>
END;
            if ($result->is_source_code && $line_number != 0 && strlen(trim($source_context)) > 0) {
                $page_content .= <<<END
    <table class="section">
        <tr><td class="section_field">Code Snippet:</td></tr>
        <tr><td class="section_content $source_code_class">$source_context</td></tr></table>        
END;
            }
            
            $page_content .= <<<END
    <table class="section">
        <tr><td class="section_field">Description:</td></tr>
        <tr><td class="section_content description">$description</td></tr></table>
    <div style="page-break-after: always;"></div>
END;

            fwrite($handle, $page_content);
        }
        fwrite($handle, $this->get_postamble());
                
        fclose($handle);
    }
    
    function get_preamble() {
        $generation_date = date('Y-m-d H:i:s');
        $version = constant("VERSION");
        $yasca =& Yasca::getInstance();
        $target_list = str_replace("/", "\\", implode("<br>", $yasca->target_list));
        $stylesheet_content = file_get_contents("etc/style.css");
        
        return <<<END
        <html>
            <head>
                <title>Yasca v$version - Report</title>
                <style type="text/css">
                    $stylesheet_content;
                </style>        
                <style>
                    .section {
                        width: 95%;
                        margin-top: 5px;
                        margin-bottom: 5px;
                    }
                    .section_field {
                        font-weight: bold;
                        background-color: black;
                        color: white;
                        border: 2px ridge grey;
                    }
                    .section_content {
                        padding-top: 5px;
                        padding-bottom: 5px;
                        padding-left: 20px;
                        background-color: white;
                        border: 1px solid darkgray;
                    }
                    .header {
                        background-color: lightyellow;
                        padding-left: 20px;
                        width: 95%;
                        border: 2px ridge grey; 
                    }
                    .header_field {
                        text-align: right;
                        font-weight: bold;
                        width: 130px;
                        padding-right: 10px;
                    }
                    .section_content li {
                        list-style-type: square;
                    }
                    .section_content h4 {
                        margin-bottom: 5px;
                    }
                    .section_content ul {
                        margin-top: 0;
                    }
                </style>
            </head>
            <body>
              <table class="report_header" cellspacing="0" cellpadding="0">
                <tr>
                    <td>Yasca Report</td></tr>
                <tr>
                    <td style="font-size: smaller;">Generated $generation_date</td></tr>
              </table>                  
END;
        }
        
        function get_postamble() {
            return <<<END
            </body>
        </html>
END;
        }   
}



?>

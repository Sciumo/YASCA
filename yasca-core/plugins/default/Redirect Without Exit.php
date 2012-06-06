<?php

/**
 * This class looks for places where Response.Redirect() is used, but not followed up with Response.End()
 * @extends Plugin
 * @package Yasca
 */
class Plugin_cwe_redirect_without_exit extends Plugin {
    public $valid_file_types = array("aspx", "vb", "asp");

    function execute() {
        for ($i=0; $i<count($this->file_contents)-1; $i++) {
            $matches = array();
            if (  preg_match('/Response\.Redirect\(/i', $this->file_contents[$i]) &&
                 !preg_match('/Response\.End\(\)/i', $this->file_contents[$i+1]) ) {
                $result = new Result();
                $result->line_number = $i+1;
                $result->severity = 4;
                $result->category = "Redirect Without Exit";
                $result->category_link = "http://cwe.mitre.org/data/definitions/698.html";
                $result->description = <<<END
                <p>
                    The web application sends a redirect to another location, but instead of exiting, it executes additional code.
                </p>
                <p>
                    <h4>References</h4>
                    <ul>
                        <li>http://cwe.mitre.org/data/definitions/698.html</li>
                    </ul>
                </p>
END;
                array_push($this->result_list, $result);
                $result = null;
                break;
            }
        }
    }
}
?>

<?php

/**
 * This class looks for XSS vulnerabilities of the form:
 *   String foo = request.getParameter("foo");
 *   ...
 *   <%= foo %>
 * @extends Plugin
 * @package Yasca
 */
class Plugin_injection_xss_jsp extends Plugin {
    public $valid_file_types = array("jsp");
    
    function execute() {
        for ($i=0; $i<count($this->file_contents); $i++) {
            if (preg_match('/String ([a-zA-Z0-9\_]+)\s*\=\s*request\.getParameter\(\s*\"(.*)\"\s*\)/', $this->file_contents[$i], $matches)) {
                $variable_name = $matches[1];
                $parameter_name = $matches[2];
                
                for ($j=$i+1; $j<count($this->file_contents); $j++) {
                    if (!preg_match('/\<\%\s*=\s*' . $variable_name . '.*\%\>/', $this->file_contents[$j])) {
                        continue;
                    }
                    $result = new Result();
                    $result->plugin_name = "Cross-Site Scripting via request.getParameter() in JSP"; 
                    $result->line_number = $j+1;
                    $result->severity = 1;
                    $result->category = "Cross-Site Scripting";
                    $result->category_link = "http://www.owasp.org/index.php/Cross_Site_Scripting";
                    $result->description = <<<END
            <p>
                Cross-Site Scripting (XSS) vulnerabilities can be exploited by an attacker to 
                impersonate or perform actions on behalf of legitimate users.
                
                This particular issue is caused by the use of <b>request.getParameter()</b> within
                Java source code. For instance, consider the following snippet:
                <code>
  String s = request.getParameter("body");
  out.println(s);
                </code>
                
                The attacker could exploit this vulnerability by directing a victim to visit a URL
                with specially crafted JavaScript to perform actions on the site on behalf of the 
                attacker, or to simply steal the session cookie. 
            </p>
            <p>
                <h4>References</h4>
                <ul>
                    <li><a href="http://www.owasp.org/index.php/XSS">http://www.owasp.org/index.php/XSS</a></li>
                    <li><a href="http://www.acunetix.com/cross-site-scripting/scanner.htm">Acunetix Web Vulnerability Scanner (<span style="color:red;font-weight:bold;">free</span>, but only does XSS scanning)</a></li>
                    <li><a href="http://www.ibm.com/developerworks/tivoli/library/s-csscript/">Cross-site Scripting article from IBM</a></li>
                </ul>
            </p>
END;

                    array_push($this->result_list, $result);                
                }
            }
        }
    }
}
?>
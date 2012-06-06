<?php

/**
 * This class looks for weak authentication values, such as:
 *  foo-username = bar
 *  foo-password = bar
 * or
 *  foo-login = quux
 *  foo-pass = quux 
 * (etc.)
 *
 * @version 1.1 (2008-12-26)
 * @extends Plugin
 * @package Yasca
 */
class Plugin_authentication_weak extends Plugin {
    public $valid_file_types = array();
    private $lookahead_length = 20;

    function execute() {
        // Handle this separately, since it's valid on all files EXCEPT those listed below
        if (Plugin::check_in_filetype($this->filename, array("java", "jsp", "class", "jar", "zip", "jpg", "png", "gif", "exe"))) {
            return;
        }
        for ($i=0; $i<count($this->file_contents); $i++) {
            $matches = array();
            if ( preg_match('/^(.{0,20})(user|username|logon|logonid|userid|login|loginid)\s*=\s*([^\s]+)/i', $this->file_contents[$i], $matches) ) {
                $prefix = $matches[1];
                $username = $matches[3];
                
                for ($j=$i+1; $j<$i+$this->lookahead_length; $j++) {
                    if (!isset($this->file_contents[$j])) break;
                    $quote = preg_quote($prefix) . "pass(word)?\s*=\s*" . preg_quote($username) . "(1|123)?";
                    $quote = str_replace("/", "\/", $quote);
                    if ( preg_match('/' . $quote . '/i', $this->file_contents[$j]) ) {
                        $result = new Result();
                        $result->line_number = $i+1;
                        $result->severity = 1;
                        $result->category = "Authentication: Weak Credentials";
                        $result->category_link = "http://www.owasp.org/index.php/Weak_credentials";
                            $result->description = <<<END
                        <p>
                            Passwords that match the associated username are extremely weak and should never be
                            used in a production environment, even if the password happens to meet the other
                            rules for password complexity. The username should never match the password. 
                        </p>
                        <p>
                            <h4>References</h4>
                            <ul>
                                <li>TODO</li>
                            </ul>
                        </p>
END;
                        array_push($this->result_list, $result);
                        $result = null;
                        break;
                    }
                }
                $username = null;
            }
            $matches = null;
        }

    }

}
?>

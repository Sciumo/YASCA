<?php
require_once("lib/common.php");
require_once("lib/Plugin.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
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
	protected $lookahead_length = 20;

	public function __construct($filename, $file_contents){
		parent::__construct($filename, $file_contents);
		// Handle this separately, since it's valid on all files EXCEPT those listed below
		// @TODO White-list checking instead of blacklist checking. An 80 meg media file in the directory will crash yasca.
		if ($this->check_in_filetype($filename, array("jar", "zip", "dll", "war", "tar", "ear",
													  "jpg", "png", "gif", "exe", "bin", "lib",
													  "svn-base", "7z", "rar", "gz",
													  "mov", "wmv", "mp3"))) {
			$this->is_valid_filetype = false;
		}
	}

	public function execute() {
		//This plugin is slow based on measurements from the profiler. Caching the counting significantly helped.
		//This plugin is still slow.
		$userids = preg_grep('/^(.{0,20})(user|username|logon|logonid|userid|login|loginid)\s*=\s*([^\s]+)/i',
							$this->file_contents);
		$count = count($this->file_contents);
		foreach ($userids as $linenumber => $line){
			$matches = array();
			preg_match('/^(.{0,20})(user|username|logon|logonid|userid|login|loginid)\s*=\s*([^\s]+)/i',
						     $line, $matches);
			$prefix = $matches[1];
			$username = $matches[3];

			if (strlen(trim($username, "\"\';")) == 0) {		// Fixed Bug #2143037
				continue;
			}

			$inner_count = min($linenumber+$this->lookahead_length, $count);
			for ($j=$linenumber+1; $j<$inner_count; $j++) {
				$quote = preg_quote($prefix) . "pass(word)?\s*=\s*" . preg_quote($username) . "(1|123)?";
				$quote = str_replace("/", "\/", $quote);
				if ( preg_match('/' . $quote . '/i', $this->file_contents[$j]) ) {
					$result = new Result();
					$result->plugin_name = "Authentication: Weak Credentials";
					$result->line_number = $linenumber+1;
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
					$this->result_list[] = $result;
					break;
				}
			}
		}

	}

}
?>
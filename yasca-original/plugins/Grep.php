<?php

/**
 * The Grep Plugin is a special plugin that faciliates .grep psuedo-plugins, which 
 * are just files in the PLUGINS directory that contain necessary information to scan
 * the target files.
 *
 * The .grep files have a specific format:
 *  name = <name of plugin> (default: "Grep: <basename of .grep file>"
 *  file_type = <list,of,extensions> (default: scan all extensions)
 *  grep = <PCRE-style regular expression, without the start and end '/' delimiters (required)
 *  category = <category name> (required)
 *  category_link = <link to more information about the issue> (default: none)
 *  severity = <severity on 1-5 scale, 1=critical, 5=informational> (default: 5)
 *  description = <description of the issue> (default: none)
 *
 * @extends Plugin
 * @package Yasca
 */ 
class Plugin_Grep extends Plugin {
    public $valid_file_types = array();
    public $name;
    public $file_type;
    public $grep = array();
    public $pre_grep;                   /* Pre Lookahead Grep - must exist within $lookahead before a $grep */
    public $lookahead_length = 10;      /* Index of pre_grep to grep must be within $lookahead_length */
    public $fix;
    public $category;
    public $category_link;
    public $severity = 5;
    public $description;
    public $preprocess;
    public $is_multi_target = true;
    
    private static $union_valid_file_types;
    
    /**
     * Re-initializes the variables back to their original state.
     */
    function initialize() {
        $this->name = "";
        $this->valid_file_types = array();
        $this->grep = array();
        $this->pre_grep = "";
        $this->lookahead_length = 10;
        $this->fix = "";
        $this->category = "";
        $this->category_link = "";
        $this->severity = 5;
        $this->description = "";
    }
    
    function execute() {
        $grep_plugin_list = array();
        $yasca =& Yasca::getInstance();
        foreach ($yasca->plugin_file_list as $grep_plugin) {
            if ( !startsWith($grep_plugin, "_") &&      // ignore plugins that start with a '_'
                 endsWith($grep_plugin, ".grep") ) {                
                array_push($grep_plugin_list, $grep_plugin);
            }
        }
        // Capture all of the valid file types
        if (!isset(Plugin_Grep::$union_valid_file_types)) {
            $yasca->log_message("Loading all scannable file types for the Grep plugin.", E_ALL);
                
            $union_valid_file_types = array();
            foreach ($grep_plugin_list as $grep_plugin) {
                $grep_content = explode("\n", file_get_contents($grep_plugin));
                $this->initialize();        // clear out $this parameters
                // Parse out the .grep files
                for ($i=0; $i<count($grep_content); $i++) {
                    $grep = trim($grep_content[$i]);
                    $matches = array();
                    if (preg_match('/\s*file_type\s*=\s*(.*)/i', $grep, $matches)) $valid_file_types = explode(",", $matches[1]);
                }
                $union_valid_file_types = array_merge($union_valid_file_types, (isset($valid_file_types) ? $valid_file_types : $this->valid_file_types));                   
            }
            $union_valid_file_types = array_values(array_unique($union_valid_file_types));          
            Plugin_Grep::$union_valid_file_types = $union_valid_file_types;
        }
        
        // check for valid file type usage
        if (!Plugin::check_in_filetype($this->filename, Plugin_Grep::$union_valid_file_types)) {
            return;
        }
        
        foreach ($grep_plugin_list as $grep_plugin) {
            $grep_content = explode("\n", file_get_contents($grep_plugin));
            $this->initialize();        // clear out $this parameters
        
            // Parse out the .grep files        
            for ($i=0; $i<count($grep_content); $i++) {
                $grep = trim($grep_content[$i]);
                $matches = array();
                if (preg_match('/^\s*name\s*=\s*(.*)/i', $grep, $matches)) $this->name = $matches[1];
                elseif (preg_match('/^\s*file_type\s*=\s*(.*)/i', $grep, $matches)) $this->valid_file_types = explode(",", $matches[1]);
                elseif (preg_match('/^\s*grep\s*=\s*(.*)/i', $grep, $matches)) array_push($this->grep, $matches[1]);
                elseif (preg_match('/^\s*pre_grep\s*=\s*(.*)/i', $grep, $matches)) $this->pre_grep = $matches[1];
                elseif (preg_match('/^\s*category\s*=\s*(.*)/i', $grep, $matches)) $this->category = $matches[1];
                elseif (preg_match('/^\s*preprocess\s*=\s*(.*)/i', $grep, $matches)) $this->preprocess = trim($matches[1]);
                elseif (preg_match('/^\s*lookahead_length\s*=\s*(.*)/i', $grep, $matches)) $this->lookahead_length = $matches[1];
                elseif (preg_match('/^\s*fix\s*=\s*(.*)/i', $grep, $matches)) $this->fix = $matches[1];
                elseif (preg_match('/^\s*category_link\s*=\s*(.*)/i', $grep, $matches)) $this->category_link = $matches[1];
                elseif (preg_match('/^\s*severity\s*=\s*(.*)/i', $grep, $matches)) $this->severity = $matches[1];
                elseif (preg_match('/^\s*description\s*=\s*(.*)/i', $grep, $matches)) {
                    $this->description = $matches[1];
                    for ($j=$i+1; $j<count($grep_content); $j++) {
                        $desc_line = $grep_content[$j];
                        if (trim($desc_line) == "END;") {
                            break;
                        } else {
                            if (trim($desc_line) == "") {
                                $desc_line = "<br/><br/>";
                            }
                            $this->description .= $desc_line . "\n";
                        } 
                    }
                }
            }

            // Check for required fields
            if (!isset($this->grep) ||
                !isset($this->category))
                break;

            // check for valid file type usage
            if (!Plugin::check_in_filetype($this->filename, $this->valid_file_types)) {
                continue;
            }

            // check for a valid preprocessor
            if (isset($this->preprocess) && !function_exists($this->preprocess)) {
                $yasca->log_message("Unable to find preprocessor function [{$this->preprocess}]. Ignoring.", E_USER_WARNING);
                unset($this->preprocess);
            }
                
            // Set the name if it isn't already specified
            if (!isset($this->name) || $this->name == "") 
                $this->name = basename($grep_plugin, ".grep");
            
            if (isset($this->pre_grep) && strlen($this->pre_grep) >= 1 ) {
                $this->grep = array_reverse($this->grep);
                array_push($this->grep, "__" . $this->pre_grep);
                $this->grep = array_reverse($this->grep);
            }

            $pre_matches = array();         // holds line numbers of pre_grep matches

            $file_contents = (isset($this->preprocess) ? call_user_func($this->preprocess, $this->file_contents) : $this->file_contents);

            foreach ($this->grep as $grep) {
                $orig_grep = $grep;
                // Massage the grep to work below
                preg_match("/\/(.*)\/([imsxUX]*)?/", $grep, $matches);
                $modifier = isset($matches[2]) ? $matches[2] : "";
                $grep = isset($matches[1]) ? $matches[1] : $grep;

                // Scan entire file at once
                $matches = array();

                $orig_error_level = error_reporting(0);
                
                $matches = preg_grep('/' . $grep . '/' . $modifier, $file_contents);

                if (preg_match("/^__(.*)/", $orig_grep)) {          // for pre_grep
                    $pre_matches = array_keys($matches);
                    continue;
                }

                error_reporting($orig_error_level);

                if (!is_array($matches)) {
                    if (!isset($yasca->general_cache["grep.ignored"]))
                        $yasca->general_cache["grep.ignored"] = array();

                    $c =& $yasca->general_cache["grep.ignored"];

                    if (!isset($c["/$grep/$modifier"])) {
                        $yasca->log_message("Invalid grep expression [/$grep/$modifier]. Ignoring.", E_USER_WARNING);
                        $c["/$grep/$modifier"] = 1;
                    }
                    continue;
                }

                foreach ($matches as $line_number => $match) {
                if ( $orig_grep == "__" . $this->pre_grep ||
                     (isset($this->pre_grep) && $this->pre_grep != "" && count($pre_matches) == 0) ||
                     (count($pre_matches) > 0 && !any_within($pre_matches, $line_number+1, $this->lookahead_length)) ) {
                    continue;
                }

                $result = new Result();
                $result->line_number = $line_number + 1;
                $result->filename = $this->filename;
                $result->severity = $yasca->get_adjusted_severity("Grep", $this->name, $this->severity);
                $result->category = $this->category;
                $result->category_link = $this->category_link;
                $result->description = $this->description; //$yasca->get_adjusted_description("Grep", $this->name, $this->description);
                $result->source = $match;
                $result->source_context = array_slice( $file_contents, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                $result->plugin_name = $yasca->get_adjusted_alternate_name("Grep", $this->name, "Grep: " . $this->name);

                if ($this->fix !== "")
                    $yasca->add_fix($result, $this->filename, $result->line_number, $match, eval($this->fix));

                array_push($this->result_list, $result);
                }
            }
        }
    }
}
?>

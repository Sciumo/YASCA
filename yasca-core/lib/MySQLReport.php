<?php
require_once("lib/common.php");
require_once("lib/Report.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
/**
 * MySQLReport Class
 *
 * This class places all contents in a MySQL Database.
 *
 * Create a database using the structure from etc/yasca.mysql.
 *
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.1
 * @license see doc/LICENSE
 * @package Yasca
 */
class MySQLReport extends Report {

    /**
     * Holds a reference to the MySQL Database
     */
    private $dbh;

    //private $canExecute = true;

    /**
     * Creates a new MySQLNativeReport object.
     */
    public function MySQLNativeReport(&$options, &$results) {
        parent::Report($options, $results);
    }

    /*
     * Opens the database.
     */
    protected function openDatabase() {
        $yasca =& Yasca::getInstance();

        $params = $yasca->options['parameter'];

        $DB_SERVER = $params["db_server"];
        $DATABASE = $params["database"];
        $DB_USERNAME = $params["db_username"];
        $DB_PASSWORD = $params["db_password"];

        if ($DB_SERVER == "" || $DATABASE == "" || $DB_USERNAME == "") {
            $yasca->log_message("Database connection details not specified. Unable to continue.", E_USER_WARNING);
            $this->dbh = false;
            return;
        }

        $this->dbh = mysql_connect($DB_SERVER, $DB_USERNAME, $DB_PASSWORD);
        if (!$this->dbh) {
            $yasca->log_message("Error creating database connection.", E_USER_WARNING);
            $this->dbh = false;
            return;
        } else {
            $yasca->log_message("Created connection to database.", E_USER_WARNING);
        }
        mysql_select_db($DATABASE, $this->dbh);
    }

    /**
     * Executes a MySQLNativeReport, to the output file $options['output'] or ./results.db
     */ 
    public function execute() {
        if (!isset($this->dbh)) $this->openDatabase();
        if (!$this->dbh) {
            Yasca::log_message("Aborting creation of MySQLReport.", E_USER_WARNING);
            return;
        } else {
            Yasca::log_message("Executing MySQLNativeReport::execute()", E_USER_WARNING);
        }
    
        $yasca =& Yasca::getInstance();
        $sth = false;

        $target_dir = "." . DIRECTORY_SEPARATOR;
        $username = $this->options['parameter']['username'];

        $target_dir = $this->options['parameter']['filename'];
        $result_id = $this->options['parameter']['result_id'];
    
        $target_dir = mysql_escape_string($target_dir);
        $result_id = is_numeric($result_id) ? $result_id : -1;
        $username = mysql_escape_string($username);
        $options = print_r($this->options, true);
        $options = mysql_escape_string($options);
       
        $result = mysql_query("insert into yasca_scan (target_dir, options, scan_dt, scan_by, result_id) values ('$target_dir', '$options', now(), '$username', $result_id)", $this->dbh);

        if (mysql_error($this->dbh)) {
            Yasca::log_message("Error inserting scan record: " . mysql_error($this->dbh), E_USER_WARNING);
        } 

        $target_id = mysql_insert_id($this->dbh);

        foreach ($this->results as $result) {
            if (!$this->is_severity_sufficient($result->severity))
                continue;

            $description_id = $this->get_description_id($result->description);
            $category_id = $this->get_category_id($result->category, $result->category_link);
            $is_source_code = $result->is_source_code ? "Y" : "N";
            $source_context = $result->source_context;
            $source_context = is_array($source_context) ? implode("\n", $source_context) : "";
            $file_modify_dt = @filemtime($result->filename);
            if (!isset($file_modify_dt) || $file_modify_dt === false) $file_modify_dt = 0;
            $file_modify_dt = date('r', $file_modify_dt);


            $result->filename = mysql_escape_string($result->filename);
            $file_modify_dt = mysql_escape_string($file_modify_dt);
            $result->source = mysql_escape_string($result->source);
            $source_context = mysql_escape_string($source_context);
        
            $result = mysql_query("insert into yasca_finding (scan_id, category_id, severity, filename, line_number, file_modify_dt, description_id, message, source_context, active_fl) values         ($target_id, $category_id, $result->severity, '{$result->filename}', $result->line_number, '$file_modify_dt', $description_id, '{$result->source}', '$source_context', 'Y')");
            if (mysql_error($this->dbh)) {
                Yasca::log_message("Error inserting scan record: " . mysql_error($this->dbh), E_USER_WARNING);
            } 
        }
        mysql_close($this->dbh);
        $this->dbh = null;
    }

    protected function get_description_id($description) {
        $description = mysql_escape_string($description);
        $result = mysql_query("select description_id from yasca_description where description='$description'", $this->dbh);
        if (mysql_num_rows($result) == 0) {
            mysql_query("insert into yasca_description (description) values ('$description')");
            return mysql_insert_id($this->dbh);
        } else {
            $row = mysql_fetch_array($result);
            return $row["description_id"];
        }
    }

    protected function get_category_id($name, $url) {
        $name = mysql_escape_string($name);
        $url = mysql_escape_string($url);
        $result = mysql_query("select category_id from yasca_category where name='$name' and url = '$url'");
        if (mysql_num_rows($result) == 0) {
            mysql_query("insert into yasca_category (name, url) values ('$name', '$url')");
            return mysql_insert_id($this->dbh);
        } else {
            $row = mysql_fetch_array($result);
            return $row["category_id"];
        }

    }

    protected function get_preamble() {
        return "";
    }
    
    protected function get_postamble() {
        return "";
    }   
}

?>
<?php

include_once("lib/Report.php");

/**
 * SQLLiteReport Class
 *
 * This class places all contents in a SQLLite Database.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.3
 * @package Yasca
 */
class SQLReport extends Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "db";

    /**
     * Holds a reference to the SQL Database
     */
    private $dbh;

    private $canExecute = true;

    public function SQLReport(&$options, &$results) {
        parent::Report($options, $results);

        /* Verify that the required libraries are available */
        if (!extension_loaded("pdo")) {
            if (getSystemOS() == "Windows") {
                if (!dl("php_pdo.dll") && !dl("resources/include/php_pdo.dll")) {
                    Yasca::log_message("PDO is required for SQLReport, but cannot be found.", E_USER_WARNING);
                    $this->canExecute = false;
                }
            } elseif (getSystemOS() == "Linux") {
                if (!dl("pdo.so") && !dl("resources/include/pdo.so")) {
                    Yasca::log_message("PDO is required for SQLReport, but cannot be found.", E_USER_WARNING);
                    $this->canExecute = false;
                }
            }
        }
        if (!extension_loaded("pdo_sqlite")) {
            if (getSystemOS() == "Windows") {
                if (!dl("php_pdo_sqlite.dll") && !dl("resources/include/php_pdo_sqlite.dll")) {
                    Yasca::log_message("PDO SQLite is required for SQLReport, but cannot be found.", E_USER_WARNING);
                    $this->canExecute = false;
                }
            } elseif (getSystemOS() == "Linux") {
                if (!dl("pdo_sqlite.so") && !dl("resources/include/pdo_sqlite.so")) {
                    Yasca::log_message("PDO SQLite is required for SQLReport, but cannot be found.", E_USER_WARNING);
                    $this->canExecute = false;
                }
            }
		}
    }

    private function openDatabase() {
        $yasca =& Yasca::getInstance();     
        
        $yasca->options["output"] = dirname($yasca->options["output"]) . "/" . basename($yasca->options["output"], ".html") . ".db";
        $yasca->options["output"] = correct_slashes($yasca->options["output"]);

        $output_file = $yasca->options["output"];

        if (!file_exists(dirname($output_file))) {
                @mkdir(dirname($output_file));
        }

        if (!file_exists($output_file)) {
            copy("resources/yasca.db", $output_file);
        }

        try {
            $this->dbh = new PDO("sqlite:" . $output_file, '', '');
        
        } catch(PDOException $e) {
        $yasca->log_message("Error creating database connection: " . $e->getMessage(), E_USER_WARNING);
        $this->dbh = false;
        return;
        }

/*
        $rows = $this->dbh->query("select 1 from target");
        if (!is_array($rows) || count($rows) == 0) {

        // Create database structure if needed
        foreach (file("resources/db.sql") as $sql) {
            $dbh->exec($sql);
        }
        }
*/
    }

    /**
     * Executes a SQLLiteReport, to the output file $options['output'] or ./results.db
     */ 
    function execute() {
        if (!isset($this->dbh)) $this->openDatabase();
        if (!$this->dbh || !$this->canExecute) {
            $yasca->log_message("Aborting creation of SQLReport.", E_USER_WARNING);
            return;
        }
        $yasca =& Yasca::getInstance();
        $sth = false;

        $target_dir = $this->options['dir'];
        $username = getenv("USERNAME");

        $sth = $this->dbh->prepare("insert into scan (target_dir, options, scan_dt, scan_by) values (?,  ?, date('now'), ?)");
        $options = print_r($yasca->options, true);

        $sth->bindParam(1, $target_dir);
        $sth->bindParam(2, $options);
        $sth->bindParam(3, $username);

        $sth->execute();

        $target_id = $this->dbh->lastInsertId();

        $this->dbh->beginTransaction();
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

            $sth = $this->dbh->prepare("insert into result (scan_id, category_id, severity, filename, line_number, file_modify_dt, description_id, message, source_context, active_fl) values (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Y')");

            $sth->bindParam(1, $target_id, PDO::PARAM_INT);
            $sth->bindParam(2, $category_id, PDO::PARAM_INT);
            $sth->bindParam(3, $result->severity, PDO::PARAM_INT);
            $sth->bindParam(4, $result->filename);
            $sth->bindParam(5, $result->line_number, PDO::PARAM_INT);
            $sth->bindParam(6, $file_modify_dt);
            $sth->bindParam(7, $description_id, PDO::PARAM_INT);
            $sth->bindParam(8, $result->source);
            $sth->bindParam(9, $source_context);

            $sth->execute();            
        }

        $this->dbh->commit();

        $this->dbh = null;
    }

    function get_description_id($description) {
        $sth = $this->dbh->prepare("select description_id from description where description=?");
        $sth->bindParam(1, $description);
        $sth->execute();
    
        $rs = $sth->fetch(PDO::FETCH_OBJ);
        if ($rs == false) {
            $sth = $this->dbh->prepare("insert into description (description) values (?)");
            $sth->bindParam(1, $description);
            $sth->execute();
            return $this->dbh->lastInsertId();
        } else {
            return $rs->description_id;
        }
    }

    function get_category_id($name, $url) {
        $sth = $this->dbh->prepare("select category_id from category where name=? and url = ?");
        $sth->bindParam(1, $name);
        $sth->bindParam(2, $url);
        $sth->execute();

        $rs = $sth->fetch(PDO::FETCH_OBJ);
        if ($rs == false) {
            $sth = $this->dbh->prepare("insert into category (name, url) values (?, ?)");
            $sth->bindParam(1, $name);
            $sth->bindParam(2, $url);
            $sth->execute();
            return $this->dbh->lastInsertId();
        } else {
            return $rs->category_id;
        }

    }

    function get_preamble() {
        return "";
    }
        
    function get_postamble() {
        return "";
    }   
}

?>
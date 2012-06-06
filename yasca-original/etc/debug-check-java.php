<?php
function check_for_java($minimum_version = 1.40) {
        $result = array();
        exec("java -version 2>&1", $result);
	print "\nFull results:";
	print_r($result);

        if (!isset($result[0])) {
	    print "Error #1 - No result found.\n";
	    return false;
	}
        if (stripos($result[0], "is not recognized") !== false) {
	    print "Error #2 - Java not found.\n";
	    return false;
	}
        $matches = array();
        if (preg_match("/\"(\d+\.\d+)/", $result[0], $matches)) {
                $version = $matches[1];
		print "Got a version ($version) - comparing.\n";
                return (floatval($version) >= floatval($minimum_version));
        }
	print "Error #3 - Did not match a version.\n";
        return false;
}

print "Calling check_for_java()\n";

print "Result=" . check_for_java();


?>
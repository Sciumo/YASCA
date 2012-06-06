<?php

    $a = array();
    if (isset($a[foo])) {
		print "bad";
    }
    if (isset($a['foo'])) {
		print "ok";
    }


	// shouldn't get flagged ID:2939351
	$mapFieldNames = $map[get_class($this)];
	
?>
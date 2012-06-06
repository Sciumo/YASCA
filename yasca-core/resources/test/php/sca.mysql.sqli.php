<?php
//
// PHP & MySQL mysql_query() vulnerable to SQLi due to tainted input.
//
if (!$link = mysql_connect('mysql_host', 'mysql_user', 'mysql_password')) {
    echo 'Could not connect to mysql';
    exit;
}

if (!mysql_select_db('mysql_dbname', $link)) {
    echo 'Could not select database';
    exit;
}

$input_one = $_GET['one'];
$input_two = $HTTP_GET_VARS['two'];
$input_three = $_POST['three'];
$input_four = $HTTP_POST_VARS['four'];
$input_five = $_REQUEST['five'];
$input_six = $_COOKIE['six'];
$input_seven = $_SERVER['seven'];
$input_eight = $HTTP_SERVER_VARS['eight'];

// test zero
$sql = "SELECT * FROM table WHERE field = '$_GET[input_zero]'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test one
$sql = "SELECT * FROM table WHERE field = '$input_one'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test two
$sql = "SELECT * FROM table WHERE field = '$input_two'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test three
$sql = "SELECT * FROM table WHERE field = '$input_three'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test four
$sql = "SELECT * FROM table WHERE field = '$input_four'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test five
$sql = "SELECT * FROM table WHERE field = '$input_five'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test six
$sql = "SELECT * FROM table WHERE field = '$input_six'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test seven
$sql = "SELECT * FROM table WHERE field = '$input_seven'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test eight
$sql = "SELECT * FROM table WHERE field = '$input_eight'";

$result = mysql_query($sql);

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);

// test nine
$result = mysql_query("select SP_StoredProcedure($_GET[input_nine])");

if (!$result) {
    echo "DB Error, could not query the database\n";
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

while ($row = mysql_fetch_assoc($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

mysql_free_result($result);
?>
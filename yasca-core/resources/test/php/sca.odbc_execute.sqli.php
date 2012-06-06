<?php 
//
// PHP & MSSQL odbc_execute() vulnerable to SQLi due to tainted input.
//

if (!$conn=odbc_connect("db_name","db_user","db_password")) {
    echo 'Could not connect to db';
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
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}


// test one
$sql = "SELECT * FROM table WHERE field = '$input_one'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test two
$sql = "SELECT * FROM table WHERE field = '$input_two'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test three
$sql = "SELECT * FROM table WHERE field = '$input_three'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test four
$sql = "SELECT * FROM table WHERE field = '$input_four'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test five
$sql = "SELECT * FROM table WHERE field = '$input_five'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test six
$sql = "SELECT * FROM table WHERE field = '$input_six'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test seven
$sql = "SELECT * FROM table WHERE field = '$input_seven'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}

// test eight
$sql = "SELECT * FROM table WHERE field = '$input_eight'";
$stmt = odbc_prepare($conn, $sql); 
$result=odbc_execute($conn, $sql);
while ($row = odbc_fetch_row($result)) {
	while(list($var, $val) = each($row)) {
        print "<B>$var</B>: $val<BR />";
	}
}


odbc_close($conn); 
?>
<?php
$devtxt = 'TEST # | ';
$thisdom = "https://www.oxex.co.uk";
$imgdom = "https://www.oxex.co.uk/";
$thisIP = "/"; # the domain (e.g. thisdom.co.uk/) if on temp url otherwise blank
$value0 = 0;
$value1 = 1;
$value2 = 2;
$value3 = 3;
$value4 = 4;
$valueblank = '';
$today  = date('Ymd');
$year = date('Y');
$thismonth = date('m');
$day = date("D");
$checkhol  = date('d-m-Y');
$sitename = 'site';
$MailHost = 'mail72.extendcp.co.uk';
$FromEmail = 'reset@oxex.co.uk';
$frompassword = 'ZZZ'; 

// Debug the incoming path - use error_log instead of echo to prevent headers already sent
error_log("Original REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set'));

// Clean up the path to get correct filename
$thispage = $_SERVER['REQUEST_URI'];
$querystr = isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '';

// if (strpos($thispage, $thisIP) !== false) {
// 	$ontempurl = 1;# flag on temp URL
// 	$thispage = str_replace($thisIP,"",$thispage);
// }

// Remove query string if present
$thispage = str_replace($querystr, "", $thispage);

// Remove any leading/trailing slashes
$thispage = trim($thispage, '/');

// Remove 'public_html' from path if present
$thispage = str_replace("oxex/public_html/", "", $thispage);

// If empty or just /, set to index.php
if (empty($thispage) || $thispage == '/') {
    $thispage = 'index.php';
}
if(isset($_SESSION['trainkey'])) {
	$trainkey = $_SESSION['trainkey'];
}

error_log("Cleaned thispage value: " . $thispage);

// Now proceed with database query
$stmt = $mysqli->prepare("SELECT pid, page_name, googleTitle, googleDesc, googleKeywords, bannerTitle, bannerTxt, page_title, page_txt1, page_txt2, page_txt3, page_txt4, page_txt5, page_txt6, page_txt7, page_txt8, page_txt9, page_txt10, page_txt11, page_txt12, image, webp, avif FROM pages_tbl WHERE filename = ?");

if (!$stmt) {
	error_log("Prepare failed: " . $mysqli->error);
} else {
	$stmt->bind_param("s", $thispage);
	
	if ($stmt->execute()) {
		$stmt->store_result();
		$stmt->bind_result(
			$page_id, $page_name, $googleTitle, $googleDesc, $googleKeywords, 
			$bannerTitle, $bannerTxt, $page_title, $page_txt1, $page_txt2, 
			$page_txt3, $page_txt4, $page_txt5, $page_txt6, $page_txt7, 
			$page_txt8, $page_txt9, $page_txt10, $page_txt11, $page_txt12, 
			$image, $webp, $avif
		);

		if ($stmt->fetch()) {
			// Success - page found
		} else {
			error_log("No record found for filename = '$thispage'");
		}
	} else {
		error_log("Execute failed: " . $stmt->error);
	}
}

$numrows = $stmt->num_rows;

$stmt->close();

$stmt = $mysqli->prepare("SELECT footerl, footerm, footerr FROM footer_tbl WHERE fid = ? ");
$stmt->bind_param("i", $value1);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($footerl, $footerm, $footerr);
$stmt->fetch();

$numrows = $stmt->num_rows;
$stmt->close();


if ($googleTitle == '') {
	$googleTitle = $devtxt."OXEX";
}
if ($googleDesc == '') {
	$googleDesc = "OXEX";
}
if ($googleKeywords == '') {
	$googleKeywords = "OXEX";
}

// set images to responsive
$page_txt1 = str_replace("<img", "<img class=\"img-fluid\"", $page_txt1);
$page_txt2 = str_replace("<img", "<img class=\"img-fluid\"", $page_txt2);
$page_txt3 = str_replace("<img", "<img class=\"img-fluid\"", $page_txt3);
$page_txt4 = str_replace("<img", "<img class=\"img-fluid\"", $page_txt4);
$page_txt5 = str_replace("<img", "<img class=\"img-fluid\"", $page_txt5);
$page_txt6 = str_replace("<img", "<img class=\"img-fluid\"", $page_txt6);

?>
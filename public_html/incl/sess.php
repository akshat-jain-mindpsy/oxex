<?php
// include_once __DIR__ . '/../OXEXfolder/config.php';
// include_once __DIR__ . '/../OXEXfolder/p_functions.php';
$devtxt = 'TEST # | ';
$db_backend = 'none';
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


// Ensure variables are defined to avoid undefined warnings
$page_id = $page_name = $googleTitle = $googleDesc = $googleKeywords = $bannerTitle = $bannerTxt = $page_title = '';
$page_txt1 = $page_txt2 = $page_txt3 = $page_txt4 = $page_txt5 = $page_txt6 = $page_txt7 = $page_txt8 = $page_txt9 = $page_txt10 = $page_txt11 = $page_txt12 = '';
$image = $webp = $avif = '';
$footerl = $footerm = $footerr = '';
$numrows = 0;

if (isset($supabase_pdo) && $supabase_pdo instanceof PDO) {
	$db_backend = 'supabase';
	try {
		$stmt = $supabase_pdo->prepare("select pid, page_name, \"googleTitle\", \"googleDesc\", \"googleKeywords\", \"bannerTitle\", \"bannerTxt\", \"page_title\", \"page_txt1\", \"page_txt2\", \"page_txt3\", \"page_txt4\", \"page_txt5\", \"page_txt6\", \"page_txt7\", \"page_txt8\", \"page_txt9\", \"page_txt10\", \"page_txt11\", \"page_txt12\", image, webp, avif from pages_tbl where filename = ? limit 1");
		$stmt->execute([$thispage]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$page_id = $row['pid'] ?? $page_id;
			$page_name = $row['page_name'] ?? $page_name;
			$googleTitle = $row['googleTitle'] ?? $googleTitle;
			$googleDesc = $row['googleDesc'] ?? $googleDesc;
			$googleKeywords = $row['googleKeywords'] ?? $googleKeywords;
			$bannerTitle = $row['bannerTitle'] ?? $bannerTitle;
			$bannerTxt = $row['bannerTxt'] ?? $bannerTxt;
			$page_title = $row['page_title'] ?? $page_title;
			$page_txt1 = $row['page_txt1'] ?? $page_txt1;
			$page_txt2 = $row['page_txt2'] ?? $page_txt2;
			$page_txt3 = $row['page_txt3'] ?? $page_txt3;
			$page_txt4 = $row['page_txt4'] ?? $page_txt4;
			$page_txt5 = $row['page_txt5'] ?? $page_txt5;
			$page_txt6 = $row['page_txt6'] ?? $page_txt6;
			$page_txt7 = $row['page_txt7'] ?? $page_txt7;
			$page_txt8 = $row['page_txt8'] ?? $page_txt8;
			$page_txt9 = $row['page_txt9'] ?? $page_txt9;
			$page_txt10 = $row['page_txt10'] ?? $page_txt10;
			$page_txt11 = $row['page_txt11'] ?? $page_txt11;
			$page_txt12 = $row['page_txt12'] ?? $page_txt12;
			$image = $row['image'] ?? $image;
			$webp = $row['webp'] ?? $webp;
			$avif = $row['avif'] ?? $avif;
			$numrows = 1;
		} else {
			error_log("No record found for filename = '$thispage' (Supabase)");
		}
	} catch (Throwable $e) {
		error_log('Supabase pages_tbl query failed: ' . $e->getMessage());
	}

	try {
		$stmt = $supabase_pdo->prepare("select footerl, footerm, footerr from footer_tbl where fid = ? limit 1");
		$stmt->execute([$value1]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$footerl = $row['footerl'] ?? $footerl;
			$footerm = $row['footerm'] ?? $footerm;
			$footerr = $row['footerr'] ?? $footerr;
		}
	} catch (Throwable $e) {
		error_log('Supabase footer_tbl query failed: ' . $e->getMessage());
	}
} else {
	error_log('No database connection available: $supabase_pdo is missing');
}


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
<script>
(function(){
try{
	console.log('[sess.php] DB backend:', <?php echo json_encode($db_backend); ?>, 'Page:', <?php echo json_encode($thispage); ?>);
}catch(e){}
})();
</script>
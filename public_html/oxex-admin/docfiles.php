<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';

$done = $_POST['done'];
$add = $_POST['add'];
$which = $_POST['which'];
// is there a thumbnail?
$imgthumb = '';
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
    $stmt = $pdo->prepare("SELECT imgthumb FROM docs_tbl WHERE did = ? ");
    $stmt->execute([$which]); 
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $imgthumb = $row ? $row['imgthumb'] : '';
}
?>
<?PHP
$ok = 1;
if ($done == 'done')	{

	echo '<p>starting upload...</p>';
	if ($_FILES['ufile']['error'] !== UPLOAD_ERR_OK) {
	    echo "Upload failed with error " . $_FILES['ufile']['error'];
	}
	$file_size = $_FILES['ufile']['size'];
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime = finfo_file($finfo, $_FILES['ufile']['tmp_name']);
	$ok = 0;
	
	switch ($mime) {
		case "image/gif";
		$ok = 1;
		$suffix= ".pdf";
		$imgtype = 0;
		break;
		case "image/jpeg";
		$ok = 1;
		$suffix= ".pdf";
		$imgtype = 0;
		break;
		case "image/png";
		$ok = 1;
		$suffix= ".pdf";
		$imgtype = 0;
		break;
		case "text/pdf";
		$ok = 1;
		$suffix= ".pdf";
		$imgtype = 1;
		break;
		case "application/pdf";
		$ok = 1;
		$suffix= ".pdf";
		$imgtype = 1;
		break;
		case "application/msword";
		$ok = 1;
		$suffix= ".doc";
		$imgtype = 2;
		break;
		case "application/wordprocessingml";
		$ok = 1;
		$suffix= ".docx";
		$imgtype = 2;
		break;
		case "application/vnd.ms-excel";
		$ok = 1;
		$suffix= ".xls";
		$imgtype = 3;
		break;
		case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
		$ok = 1;
		$suffix= ".xlsx";
		$imgtype = 3;
		break;
		case "application/zip";
		$ok = 1;
		$suffix= ".zip";
		$imgtype = 5;
		break;
		case "application/x-zip";
		$ok = 1;
		$suffix= ".zip";
		$imgtype = 5;
		break;
		case "application/x-zip-compressed";
		$ok = 1;
		$suffix= ".zip";
		$imgtype = 5;
		break;
		case "application/powerpoint";
		$ok = 1;
		$suffix= ".ppt";
		$imgtype = 4;
		break;
		case "application/vnd.ms-powerpoint";
		$ok = 1;
		$suffix= ".ppt";
		$imgtype = 4;
		break;
		case "application/vnd.openxmlformats-officedocument.presentationml.presentation";
		$ok = 1;
		$suffix= ".pptx";
		$imgtype = 4;
		break;

	   default:
	       die("Unknown/not permitted file type");
	}
	echo "file is $suffix $ok ($mime)";
//move_uploaded_file(...);
if ($ok == 1) {
	define ("FILEREPOSITORY","../docs/");
	$generateID = substr(md5(rand()), 0, 15);
	$filename = $generateID.$suffix;
	//move_uploaded_file($_FILES["ufile"]["tmp_name"], "../docs/" . $_FILES["ufile"]["filename"]);
	$result = move_uploaded_file($_FILES['ufile']['tmp_name'], FILEREPOSITORY."$filename");
	echo "<p>File successfully uploaded.</p>";
	$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
	if ($pdo) {
		$stmt = $pdo->prepare("UPDATE docs_tbl SET filename = ?, filetype = ? WHERE did = ?"); 
		$stmt->execute([$filename, $imgtype, $which]);
	}
	// if pdf and no thumb exists, create thumb
	if ($imgtype == 1 && $imgthumb == '') {
		// create thumbnail if pdf
		$startpdf = "../docs/".$filename."[0]";
		$largepng = "../docthumbs/".$generateID.".png";
		// create png image and thumb from pdf first page
		$result1 = exec("/usr/bin/convert  \"$startpdf\" -quality 70 -colorspace rgb -background white -flatten -thumbnail 180x270 \"$largepng\"");

		$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
		if ($pdo) {
			$stmt = $pdo->prepare("UPDATE docs_tbl SET imgthumb = ? WHERE did = ?"); 
			$stmt->execute([$generateID, $which]);
		}
		echo "<p>(start $startpdf) (largepng $largepng) (result1 $result1) Thumbnail created</p>";
		echo "<img src=\"$largepng\" width=\"100\">";

	}
}

};#end if $done
echo "end";
?>


<?PHP

	// show current pdfs
/*
echo "<p><span class=\"btn btn-danger\"><a href=\"docdetail.php?which=$which&amp;del=delpdf\"> <i class=\"fa fa-times-circle\"></i>   Delete the pdf</a></span></p>";
echo "<p><span class=\"btn btn-default\"><a href=\"../docs/$generateID\"><i class=\"fa fa-file-pdf-o fa-lg\"></i> Right Click to open PDF</a></span></p>";
echo '<p>&nbsp;</p>';
*/
?>
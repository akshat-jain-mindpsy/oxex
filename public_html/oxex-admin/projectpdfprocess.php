<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
$valueblank = '';
$value0 = 0;
$value1 = 1;
$value2 = 2;
$which = isset($_POST['which']) ? $_POST['which'] : 0;
// is there already an image?
$eventlogo = '';
$stmt = $mysqli->prepare("SELECT eventlogo FROM events_tbl WHERE ID =  ? ");
$stmt->bind_param("i", $which); 
$stmt->bind_result($eventlogo);
$stmt->execute();
$stmt->fetch();
$stmt->close();
echo '<p class="main">Starting upload procedure...</p>';
$generateID = substr(md5(rand()), 0, 16); 
define ("FILEREPOSITORY","../perfpdfs/");
 if (is_uploaded_file($_FILES['ufile']['tmp_name'])) {
  $uploadtype = ($_FILES["ufile"]["type"]);
    if ($uploadtype == "application/pdf") {
      $suffix= ".pdf";
      $imgtype = "1";
       
       $numids=1;

        while (!$numids == 0) {
          // make 16 digit hex string plus suffix
          $generateID = substr(md5(rand()), 0, 16).'.pdf';
          $stmt = $mysqli->prepare("SELECT ID FROM events_tbl WHERE eventpdf = ? LIMIT 1");
          $stmt->bind_param('s', $generateID);
          $stmt->execute();
          $stmt->store_result();
          $stmt->bind_result($fid);
          $stmt->fetch();
          $numids = $stmt->num_rows;
          $stmt->close();
        }

        $resultpdf = move_uploaded_file($_FILES['ufile']['tmp_name'], FILEREPOSITORY."$generateID");
         
        if ($resultpdf == 1) { 
             $errmsg = "<p>File successfully uploaded.</p>";
             
            $stmt = $mysqli->prepare("UPDATE events_tbl SET eventpdf = ? WHERE ID = ?"); 
            $stmt->bind_param("si", $generateID, $newid);
            $stmt->execute();
            $stmt->close();
            // create thumbnail if pdf and no image
            if ($eventlogo != '') {
              $startpdf = "../perfpdfs/".$generateID;
              $largepng = "../performances/".$generateID.".jpg";
              $pngname = $generateID.".jpg";
              // create jpg image and thumb from pdf first page
              $result1 = exec("/usr/bin/convert  \"$startpdf\" -quality 70 -colorspace rgb -background white -flatten -thumbnail 180x270 \"$largepng\"");
              
              $stmt = $mysqli->prepare("UPDATE events_tbl SET eventlogo = ? WHERE ID = ?"); 
              $stmt->bind_param("si", $pngname, $newid);
              $stmt->execute();
              $stmt->close();
            }
        }
         else { $errmsg = "<p>There was a problem uploading the file.  $name ($uploadtype)</p>";
        }
  
        //$errmsg = "uploadtype $uploadtype | suffix $suffix | name $name";
    } else {
       $errmsg = "<p>Files must be uploaded in PDF format.  $name ($uploadtype)</p>";
    }

 }
 if ($result == true)
 {
	$stmt = $mysqli->prepare("UPDATE events_tbl SET eventlogo = ? WHERE ID = ?"); 
	$stmt->bind_param("si", $actualname, $which);
	$stmt->execute();
	$stmt->close();
		echo "<p><a href=\"performancedetail.php?which=$which&amp;delicon=delicon&amp;photo=$whevattid\"<span class=\"btn btn-danger\"> <i class=\"fa fa-times-circle\"></i> Delete Image</span></a></p>";
		echo "<p><a href=\"../perfpdfs/$actualname\" class=\"btn btn-green\">download</a></p>";
		echo '<p>&nbsp;</p><hr>';
 } 
 echo $result;
?>
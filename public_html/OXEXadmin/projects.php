<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Case Studies";
$subtitle = "Case Studies";
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
</head>
<?php 
$sort_order = 1;
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AD' || $admintype == 'DV')) {
  // delete any links
  $stmt = $mysqli->prepare("DELETE FROM proj_link_tbl WHERE pid = ?");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  $stmt->close();
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM proj_tbl WHERE pid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();
}

if ($newadmin == 'newadmin') {
  $proj_name = isset($_POST['proj_name']) ? $_POST['proj_name'] : '';
  $catid = isset($_POST['catid']) ? $_POST['catid'] : 0;
  $page_title = isset($_POST['page_title']) ? $_POST['page_title'] : '';
  $page_txt1 = isset($_POST['page_txt1']) ? $_POST['page_txt1'] : '';
  $page_txt2 = isset($_POST['page_txt2']) ? $_POST['page_txt2'] : '';
  $page_txt3 = isset($_POST['page_txt3']) ? $_POST['page_txt3'] : '';
  $page_txt4 = isset($_POST['page_txt4']) ? $_POST['page_txt4'] : '';
  $page_txt5 = isset($_POST['page_txt5']) ? $_POST['page_txt5'] : '';
  $page_txt6 = isset($_POST['page_txt6']) ? $_POST['page_txt6'] : '';
  $page_txt12 = isset($_POST['page_txt12']) ? $_POST['page_txt12'] : '';
  $proj_vid = isset($_POST['proj_vid']) ? $_POST['proj_vid'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  $google_priority = isset($_POST['google_priority']) ? $_POST['google_priority'] : 0;

  // format the string to be used as a URL
  $semantic_url = preg_replace('/[^A-Za-z0-9-]+/', '-', $proj_name);# replace non alphanumeric with a dash
  $semantic_url = preg_replace('/-+/', '-', $semantic_url);# remove repeated dashes
  $semantic_url = rtrim($semantic_url, '-');# remove trailing dash
  // look for any titles with the same url in semantic_url
  $semantic_title = $semantic_url; # not used
  $stmt = $mysqli->prepare("SELECT pid FROM proj_tbl WHERE semantic_url = ?");
  $stmt->bind_param("s", $semantic_url);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($pid);
  $stmt->fetch();
  $numrows = $stmt->num_rows;
  $stmt->close();

  $urllen = strlen($semantic_url);
  if ($urllen > 255 && $numrows == 0) {
    $semantic_url = substr($semantic_url, 0, 255); # don't want more than 255 chars
  }
  if ($urllen > 250 && $numrows > 0) {
    $semantic_url = substr($semantic_url, 0, 250); # don't want more than 251 chars as we need to append a suffix
  }
  if ($numrows > 0) {
    $randsuffix = substr(md5(rand()), 0, 4);
    $semantic_url = $semantic_url.'-'.$randsuffix;# add suffix to make it different to existing 
  }
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO proj_tbl (catid, proj_vid, sort_order, proj_name, semantic_url, page_title, page_txt1, page_txt2, page_txt3, page_txt4, page_txt5, page_txt6, page_txt12, google_priority, date_added, date_modified, projpdf, pdfthumb) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("isisssssssssssiiss", $catid, $proj_vid, $sort_order, $proj_name, $semantic_url, $page_title, $page_txt1, $page_txt2, $page_txt3, $page_txt4, $page_txt5, $page_txt6, $page_txt12, $value0, $today, $today, $valueblank, $valueblank);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
  $insert_stmt->close();
  

  if (is_uploaded_file($_FILES['Image1']['tmp_name'])) {
    // create project pdf thumb and add filename
    $generateID = substr(md5(rand()), 0, 16); 
    ini_set('max_execution_time', 300);
   function resize_save_jpeg( $source_image_path, $target_image_path, $target_image_width, $target_image_height ) {
    list( $source_image_width, $source_image_height, $source_image_type ) = getimagesize( $source_image_path );
    switch ( $source_image_type ) {
     case IMAGETYPE_GIF:
      $source_gd_image = imagecreatefromgif( $source_image_path );
      break;
     case IMAGETYPE_JPEG:
      $source_gd_image = imagecreatefromjpeg( $source_image_path );
      break;
     case IMAGETYPE_PNG:
      $source_gd_image = imagecreatefrompng( $source_image_path );
      break;
    }

    if ( $source_gd_image === false ) {
     return false;
    }

    $source_aspect_ratio = $source_image_width / $source_image_height;
    $target_aspect_ratio = $target_image_width / $target_image_height;
    if ( ($source_image_width <= $target_image_width) && ($source_image_height <= $target_image_height) )  {
     $target_image_width = $source_image_width;
     $target_image_height = $source_image_height;
    }  elseif ( $target_aspect_ratio > $source_aspect_ratio )   {
     $target_image_width = ( int ) ( $target_image_height * $source_aspect_ratio );
    }   else  {
     $target_image_height = ( int ) ( $target_image_width / $source_aspect_ratio );
    }

    $target_gd_image = imagecreatetruecolor( $target_image_width, $target_image_height );
    imagecopyresampled( $target_gd_image, $source_gd_image, 0, 0, 0, 0, $target_image_width, $target_image_height, $source_image_width, $source_image_height );
    imagejpeg( $target_gd_image, $target_image_path, 90 );
    imagedestroy( $source_gd_image );
    imagedestroy( $target_gd_image );
    return true;

   }

   $temp_image_path = $_FILES[ 'Image1' ][ 'tmp_name' ];
   $temp_image_name = $_FILES[ 'Image1' ][ 'name' ];
   list( , , $temp_image_type ) = getimagesize( $temp_image_path );
   if ( $temp_image_type === NULL ) {
    return false;
   }

   switch ( $temp_image_type ) {
    case IMAGETYPE_GIF:
     break;
    case IMAGETYPE_JPEG:
     break;
    case IMAGETYPE_PNG:
     break;
    default:
     return false;
   }
   
   $large_image_path = '../projects/'.$generateID.'.jpg';
      $actualname = $generateID.'.jpg';
      $result = resize_save_jpeg( $temp_image_path, $large_image_path, 400, 400 );
   $isanimage = 0;
   if ( $result )
   {
    $stmt = $mysqli->prepare("UPDATE proj_tbl SET pdfthumb = ? WHERE pid = ?"); 
    $stmt->bind_param("si", $actualname, $newid);
    $stmt->execute();
    $stmt->close();
    $isanimage = 1;
   }
  }# if image uploaded

  define ("FILEREPOSITORY","../projpdfs/");
 if (is_uploaded_file($_FILES['ufile']['tmp_name'])) {
  $uploadtype = ($_FILES["ufile"]["type"]);
    if ($uploadtype == "application/pdf") {
      $suffix= ".pdf";
      $imgtype = "1";
       
       $numids=1;
        while (!$numids == 0) {
          // make 16 digit hex string plus suffix
          $generateID = substr(md5(rand()), 0, 16).'.pdf';
          $stmt = $mysqli->prepare("SELECT pid FROM proj_tbl WHERE projpdf = ? LIMIT 1");
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
             
            $stmt = $mysqli->prepare("UPDATE proj_tbl SET projpdf = ? WHERE pid = ?");
            $stmt->bind_param("si", $generateID, $newid);
            $stmt->execute();
            $stmt->close();
            // create thumbnail if pdf and no image
            if ($isanimage == 0) {
              $startpdf = "../projpdfs/".$generateID;
              $largepng = "../projects/".$generateID.".jpg";
              $pngname = $generateID.".jpg";
              // create jpg image and thumb from pdf first page
              $result1 = exec("/usr/bin/convert  \"$startpdf\" -quality 70 -colorspace rgb -background white -flatten -thumbnail 180x270 \"$largepng\"");
              
              $stmt = $mysqli->prepare("UPDATE proj_tbl SET pdfthumb = ? WHERE pid = ?"); 
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



  
}

$todaydisp = strtotime($today); # default 'to' date for datepicker
?>
<body>
   <div class="wrapper">
      <!-- top, left side and right navbars -->
      <?php include 'incl/topbar.php' ?>
      <?php include 'incl/sidebar.php' ?>
      <?php include 'incl/offsidebar.php' ?>

      <!-- Main section-->
      <section class="section-container">
         <!-- Page content-->
         <div class="content-wrapper">
            <div class="content-header">
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                      <table class="table table-striped my-4 w-100" id="maintable">
                         <thead>
                            <tr>
                               <th class="sort-alpha" data-priority="1">Vehicle</th>
                                <th>Manufacturer</th>
                                <th>Images</th>
                                <th>Video</th>
                                <th>Sort Order</th>
                                <th class="sort-numeric">Mod Date</th>
                            </tr>
                         </thead>
                         <tbody>
<?PHP
$stmt = $mysqli->prepare("SELECT pid, proj_name, proj_vid, date_modified, sort_order FROM proj_tbl");
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($pid, $proj_name, $proj_vid, $date_modified, $sort_order);
while ($stmt->fetch()){
  $date_modified = strtotime($date_modified);
  // get categories
  // Loop through tags
  $cats = '';
  $loopstmt = $mysqli->prepare("SELECT catid FROM proj_link_tbl  WHERE pid = ?");
  $loopstmt->bind_param("i", $pid);
  $loopstmt->execute();
  $loopstmt->store_result();
  $loopstmt->bind_result($catid);
  while ($loopstmt->fetch()) {
    $whatlink = $mysqli->prepare("SELECT catproj FROM proj_cats WHERE catid = ? ");
    $whatlink->bind_param("i", $catid);
    $whatlink->execute(); 
    $whatlink->bind_result($catproj);
    $whatlink->fetch();
    $cats .= $catproj.' ';
    $whatlink->close();

  }
  $loopstmt->close();
  // get images
  $numimg = 0;
  $galleryid = 'r'.$pid;
  $vids = $mysqli->prepare("SELECT gid FROM cs_gallery WHERE category = ? ");
  $vids->bind_param("i", $galleryid);
  $vids->execute();
  $vids->store_result();
  $numimg = $vids->num_rows;
  $vids->close();
  if ($numimg == 0) {
   $cats = '<div class="badge badge-danger">None</div>';
  }
  if ($sort_order == 0) {
   $dispsort_order = '<div class="badge badge-danger">Not displayed</div>';
  } else {
    $dispsort_order = $sort_order;
  }
  if ($cats == '') {
   $cats = '<div class="badge badge-danger">None</div>';
  }
  if ($proj_vid == '') {
   $proj_vid = '<div class="badge badge-danger">No</div>';
  } else {
    $proj_vid = '<div class="badge badge-success">Yes</div>';
  }

?>
<tr>
 <td><a href="projectdetail.php?which=<?php echo $pid ?>"><?php echo $proj_name ?></a></td>
 <td><?php echo $cats ?></td>
 <td><?php echo $numimg ?></td>
 <td><?php echo $proj_vid ?></td>
 <td><?php echo $dispsort_order ?></td>
 <td><?php echo date('j M Y',$date_modified) ?></td>

</tr>
<?php
}
$numrows = $stmt->num_rows;
$stmt->close();
?>
                         </tbody>
                      </table>
                   </div>
               </div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Case Study</div>
                        </div>
                        <div class="card-body">
                           <div class="form-group">
                            <label class="col-form-label" for="catid">Manufacturer</label>
                                <select class="custom-select custom-select-lg mb-3" id="catid" name="catid" required>
                                  <option selected="">Select a Manufacturer:</option>
                                  <?php
                                  $pageid = 0; # defualt
                                  $tableset = $mysqli->prepare("SELECT catid, catproj FROM proj_cats ");
                                  $tableset->execute();
                                  $tableset->store_result();
                                  $tableset->bind_result($catid, $catproj);
                                  while ($tableset->fetch()){
                                    echo "<option value=\"e$catid\"";
                                    if ($pageid == $catid) {
                                      echo "selected='selected'";
                                    }
                                    echo ">$catproj</option>";
                                  }
                                  $tableset->close();
                                  ?>
                                </select>
                          </div>
                          <div class="form-group">
                           <label class="col-form-label" for="proj_name">Vehicle type</label>
                           <input type="text" class="form-control" id="proj_name" name="proj_name" required  onchange="showUser(this.value)">
                              <span class="form-text" id="txtHint">Case Study URL will be displayed here...</span>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_title">Page Title</label>
                              <input class="form-control" type="text" id="page_title" name="page_title">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="proj_vid">Vimeo Share Embed iFrame</label>
                              <textarea class="form-control" type="text" id="proj_vid" name="proj_vid"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt1">Text block 1</label>
                              <textarea class="form-control summernote" type="text" id="page_txt1" name="page_txt1"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt2">Text block 2</label>
                              <textarea class="form-control summernote" type="text" id="page_txt2" name="page_txt2"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt3">Text block 3</label>
                              <textarea class="form-control summernote" type="text" id="page_txt3" name="page_txt3"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt4">Text block 4</label>
                              <textarea class="form-control summernote" type="text" id="page_txt4" name="page_txt4"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt5">Text block 5</label>
                              <textarea class="form-control summernote" type="text" id="page_txt5" name="page_txt5"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt6">Text block 6</label>
                              <textarea class="form-control summernote" type="text" id="page_txt6" name="page_txt6"></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="page_txt12">Admin Notes</label>
                              <textarea class="form-control summernote" type="text" id="page_txt12" name="page_txt12"></textarea>
                              <span class="form-text"><em>Admin notes not displayed on website</em></span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label">Images</label>
                              <p>Images added in Page Content &gt; <a href="gallery">Gallery</a>. Select sort order '1' for main image on Project page.</p>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label">PDF Thumbnail Image</label>
                              <input type="file" class="form-control" name="Image1" id="Image1">
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Project PDF</label>
                            <input type="file" class="form-control" name="ufile" id="ufile"><span class="form-text">A thumbnail of the pdf wil be created if no image is loaded</span>
                          </div>
                           
                          

                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
        "pageLength": 10
      });
     $('.summernote').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
        toolbar: [
           ['style', ['style']],
           ['font', ['bold', 'underline', 'superscript', 'subscript']],
           ['color', ['color']],
           ['para', ['ul', 'ol', 'paragraph']],
           ['table', ['table']],
           ['insert', ['link', 'picture', 'video']],
           ['view', ['codeview', 'help']],
         ]
      });
   });
   </script>
   <script>
  function showUser(str) {
      if (str == "") {
          document.getElementById("txtHint").innerHTML = "";
          return;
      } else { 
          if (window.XMLHttpRequest) {
              // code for IE7+, Firefox, Chrome, Opera, Safari
              xmlhttp = new XMLHttpRequest();
          } 
          xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                  document.getElementById("txtHint").innerHTML = this.responseText;
              }
          };
          xmlhttp.open("GET","createprojquerystring.php?q="+str,true);
          xmlhttp.send();
      }
  }
  </script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
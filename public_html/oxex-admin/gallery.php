<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Gallery";
$subtitle = "Gallery";
if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AD' || $admintype == 'DV')) {
  // get the  title to display
  $stmt = $mysqli->prepare("SELECT caption, filename FROM cs_gallery WHERE gid = ?");
  $stmt->bind_param("i", $which);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($caption_del, $filename_id_del);
  $stmt->fetch();
  $stmt->close();

  // delete 
  $stmt = $mysqli->prepare("DELETE FROM cs_gallery WHERE gid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Image $caption_del ($filename_id_del) deleted</strong></div></div></div>";
  }
  $stmt->close();

}
$sort_order = 1;
if ($newadmin == 'newadmin') {
  $category = isset($_POST['category']) ? $_POST['category'] : '';
  $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
    $sort_order = (int)$sort_order;
  
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO cs_gallery (sort_order, filename, caption, date_created, category) VALUES (?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("issis", $sort_order, $valueblank, $caption, $today, $category);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
  $insert_stmt->close();
  $sort_order++;
  $lastcategory = $category;

  if (is_uploaded_file($_FILES['Image1']['tmp_name'])) {
    // create images and add filename
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
   
   $large_image_path = '../gallery/'.$generateID.'.jpg';
   $small_image_path = '../thumb/'.$generateID.'.jpg';
      $actualname = $generateID.'.jpg';
      $result = resize_save_jpeg( $temp_image_path, $large_image_path, 1000, 1000 );
      $result = resize_save_jpeg( $temp_image_path, $small_image_path, 400, 400 );
   
   if ( $result )
   {

    $stmt = $mysqli->prepare("UPDATE cs_gallery SET filename = ? WHERE gid = ?"); 
    $stmt->bind_param("si", $actualname, $newid);
    $stmt->execute();
    $stmt->close();
    
   }
  }# if file uploaded
}
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
                                 <th class="sort-alpha" data-priority="1">File</th>
                                 <th class="sort-alpha" data-priority="2">Section</th>
                                 <th class="sort-alpha" data-priority="2">Page</th>
                                 <th class="sort-numeric">Date</th>
                                 <th class="sort-numeric">Sort Order</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$where = '';
$tableset = $mysqli->prepare("SELECT gid, filename, sort_order, category, date_created FROM cs_gallery ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($gid, $filename, $tablesort_order, $category, $date_created);
while ($tableset->fetch()){
  $date_created = strtotime($date_created);
  if ($tablesort_order == 0) {
    $tablesort_order = '<div class="badge badge-danger">Not displayed</div>';
  }
  $where = substr($category, 0, 1); # first char
  $pageid = ltrim($category, $where); # remaining id ref
  $dispcat = "Gallery"; # default = Gallery
  $dispage = 'N/A'; # all on one page
  if ($filename == '') {
    $filename = '[none]';
  }

  if ($where == 'p') {
    $dispcat = "Pages";
    $stmt = $mysqli->prepare("SELECT page_name FROM pages_tbl WHERE pid = ?");
    $stmt->bind_param("i", $pageid);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($dispage);
    $stmt->fetch();
    $stmt->close();
  }
  if ($where == 'b') {
    $dispcat = "Blog";
    $stmt = $mysqli->prepare("SELECT blog_title FROM semantic_blog WHERE sbid = ?");
    $stmt->bind_param("i", $pageid);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($dispage);
    $stmt->fetch();
    $stmt->close();
  }
?>
<tr>
   <td><a href="gallerydetail.php?which=<?php echo $gid ?>"><?php echo $filename ?></a></td>
   <td><?php echo $dispcat ?></td>
   <td><?php echo $dispage ?></td>
   <td><?php echo date('j M Y',$date_created) ?></td>
   <td><?php echo $tablesort_order ?></td>
</tr>
 <?php
 }
$tableset->close();
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
                           <div class="card-title">Add new Image</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="caption">Caption</label>
                              <input class="form-control" type="text" id="caption" name="caption">
                           </div>
                           <div class="form-group">
                            <label class="col-form-label" for="category">Which Page</label>
                                <select class="custom-select custom-select-lg mb-3" id="category" name="category" required>
                                  <option selected="">Select a Destination:</option>
                                  <?php
                                  //echo "<option value=\"g0\"";
                                  //  if ($where == 'g') {
                                  //    echo "selected='selected'";
                                  //  }
                                  //echo ">Gallery</option>";

                                  // list pages
                                  $tableset = $mysqli->prepare("SELECT pid, page_name FROM pages_tbl ");
                                  $tableset->execute();
                                  $tableset->store_result();
                                  $tableset->bind_result($pid, $page_name);
                                  while ($tableset->fetch()){
                                    echo "<option value=\"p$pid\"";
                                    if ($where == 'p' && $pageid == $pid) {
                                      echo "selected='selected'";
                                    }
                                    echo ">Page - $page_name</option>";
                                  }
                                  $tableset->close();
                                  
                                  
                                  // list blog
                                  $tableset = $mysqli->prepare("SELECT sbid, blog_title FROM semantic_blog ");
                                  $tableset->execute();
                                  $tableset->store_result();
                                  $tableset->bind_result($sbid, $blog_title);
                                  while ($tableset->fetch()){
                                    echo "<option value=\"b$sbid\"";
                                    if ($where == 'b' && $pageid == $sbid) {
                                      echo "selected='selected'";
                                    }
                                    echo ">Blog - $blog_title</option>";
                                  }
                                  $tableset->close();
                                  ?>
                                </select>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label" for="sort_order">Sort order</label>
                              <input class="form-control" type="number" id="sort_order" name="sort_order" value="<?php echo $sort_order ?>" min="0" max="999"><span class="form-text">Enter 0 if not to be displayed</span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label">Image</label>
                              <input type="file" class="form-control" name="Image1" id="Image1">
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
        "pageLength": 25
      });
   });
   </script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
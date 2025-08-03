<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Manufacturers";
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
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM proj_cats WHERE catid = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();

}

if ($newadmin == 'newadmin') {
  $catproj = isset($_POST['catproj']) ? $_POST['catproj'] : '';
  // format the string to be used as a URL
  $semantic_url = preg_replace('/[^A-Za-z0-9-]+/', '-', $catproj);# replace non alphanumeric with a dash
  $semantic_url = preg_replace('/-+/', '-', $semantic_url);# remove repeated dashes
  $semantic_url = rtrim($semantic_url, '-');# remove trailing dash
  // look for any titles with the same url in semantic_url
  $semantic_title = $semantic_url; # not used
  $stmt = $mysqli->prepare("SELECT catid FROM proj_cats WHERE semantic_url = ?");
  $stmt->bind_param("s", $semantic_url);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($catid);
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

  $cat_txt = isset($_POST['cat_txt']) ? $_POST['cat_txt'] : '';
  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO proj_cats (catproj, semantic_url, cat_txt, cat_logo, webp, avif) VALUES (?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("ssssss", $catproj, $semantic_url, $cat_txt, $valueblank, $valueblank, $valueblank);
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
   
   $large_image_path = '../logos/'.$generateID.'.jpg';
      $actualname = $generateID.'.jpg';
      $result = resize_save_jpeg( $temp_image_path, $large_image_path, 400, 400 );
   $isanimage = 0;
   if ( $result )
   {
    $stmt = $mysqli->prepare("UPDATE proj_cats SET cat_logo = ? WHERE catid = ?"); 
    $stmt->bind_param("si", $actualname, $newid);
    $stmt->execute();
    $stmt->close();
    $isanimage = 1;
   }
  }# if image uploaded
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
                                 <th class="sort-alpha" data-priority="1">Manufacturers</th>
                                 <th>Text?</th>
                                 <th>Logo?</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$tableset = $mysqli->prepare("SELECT catid, catproj, cat_logo, cat_txt FROM proj_cats ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($catid, $catproj, $cat_logo, $cat_txt);
while ($tableset->fetch()){
   if ($cat_logo == '') {
      $cat_logo = '<div class="badge badge-warning">No</div>';
   } else {
      $cat_logo = '<div class="badge badge-info">Yes</div>';
   }
   if ($cat_txt == '') {
      $cat_txt = '<div class="badge badge-warning">No</div>';
   } else {
      $cat_txt = '<div class="badge badge-info">Yes</div>';
   }
?>
<tr>
   <td><a href="categorydetail.php?which=<?php echo $catid ?>"><?php echo $catproj ?></a></td>
    <td><?php echo $cat_txt ?></td>
     <td><?php echo $cat_logo ?></td>
</tr>
 <?php
 }
$numrows = $tableset->num_rows;
$tableset->close();
?>
                           </tbody>
                        </table>
                     </div>
               </div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Manufacturer</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="catproj">Manufacturer</label>
                              <input class="form-control" type="text" id="catproj" name="catproj">
                           </div>
                           <div class="form-group">
                            <label class="col-form-label" for="cat_txt">Optional Text</label>
                              <textarea name="cat_txt" id="cat_txt" class="form-control summernote"></textarea>
                          </div>
                           <div class="form-group">
                              <label class="col-form-label">Logo</label>
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
        "pageLength": 10
      });
     $('.summernote').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
        cleaner:{
              action: 'both', // both|button|paste 'button' only cleans via toolbar button, 'paste' only clean when pasting content, both does both options.
              newline: '<br>', // Summernote's default is to use '<p><br></p>'
              notStyle: 'position:absolute;top:0;left:0;right:0', // Position of Notification
              icon: '<i class="note-icon">[Your Button]</i>',
              keepHtml: true, // Remove all Html formats
              keepOnlyTags: ['<p>', '<br>', '<ul>', '<li>', '<b>', '<strong>','<i>', '<a>'], // If keepHtml is true, remove all tags except these
              keepClasses: false, // Remove Classes
              badTags: ['style', 'script', 'applet', 'embed', 'noframes', 'noscript', 'html'], // Remove full tags with contents
              badAttributes: ['style', 'start'], // Remove attributes from remaining tags
              limitChars: false, // 0/false|# 0/false disables option
              limitDisplay: 'both', // text|html|both
              limitStop: false // true/false
        },
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
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
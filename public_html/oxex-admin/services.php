<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Services";
$subtitle = "Page content";
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
  // delete row from detail page
  $stmt = $mysqli->prepare("DELETE FROM stafflist WHERE slil = ? LIMIT 1");
  $stmt->bind_param("i", $which); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();
}

if ($newadmin == 'newadmin') {
  $panel_title = isset($_POST['panel_title']) ? $_POST['panel_title'] : '';
  $panel_txt = isset($_POST['panel_txt']) ? $_POST['panel_txt'] : '';
  $panel_btn_txt = isset($_POST['panel_btn_txt']) ? $_POST['panel_btn_txt'] : '';
  $panel_btn_link = isset($_POST['panel_btn_link']) ? $_POST['panel_btn_link'] : '';
  $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 0;
  $lastsort = $sort_order + 1;

  $insert_stmt = $mysqli->prepare("INSERT INTO panel_tbl (panel_title, panel_txt, panel_btn_txt, panel_btn_link, image, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("sssssi", $panel_title, $panel_txt, $panel_btn_txt, $panel_btn_link, $valueblank, $sort_order);
  $insert_stmt->execute();
  $newid =  $insert_stmt->insert_id;
  $insert_stmt->close();

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
      imagejpeg( $target_gd_image, $target_image_path, 80 );
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
     
     $large_image_path = '../services/'.$generateID.'.jpg';
        $actualname = $generateID.'.jpg';

     $result = resize_save_jpeg( $temp_image_path, $large_image_path, 1000, 1000 );
     if ( $result )
     {
      $stmt = $mysqli->prepare("UPDATE panel_tbl SET image = ? WHERE paid = ?"); 
      $stmt->bind_param("si", $actualname, $newid);
      $stmt->execute();
      $stmt->close();
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
                                <th class="sort-alpha" data-priority="1">Panel Title</th>
                                <th>Button Text</th>
                                <th>Button Link</th>
                                <th class="sort-numeric">Sort order</th>
                                <th>Image?</th>
                            </tr>
                         </thead>
                         <tbody>
<?PHP
$stmt = $mysqli->prepare("SELECT paid, panel_title, panel_btn_txt, panel_btn_link, image, sort_order FROM panel_tbl");
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($paid, $panel_title, $panel_btn_txt, $panel_btn_link, $image, $sort_order);
while ($stmt->fetch()){
  if ($image == '' || $image == '0') {
    $vidqty = '<span class="btn btn-danger">No</span>';
  } else {
    $vidqty = '<span class="btn btn-success">Yes</span>';
  }
  if ($sort_order == 0) {
   $sort_order = '<div class="badge badge-danger">Not displayed</div>';
  }

?>
<tr>
  <td valign="top"><a href="servicedetail.php?which=<?php echo $paid ?>&amp;change=no"><?php echo $panel_title ?></a></td>
  <td valign="top"><?php echo $panel_btn_txt ?></td>
  <td valign="top"><?php echo $panel_btn_link ?></td>
  <td valign="top"><?php echo $sort_order ?></td>
  <td valign="top"><?php echo $vidqty ?></td>
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
                           <div class="card-title">Add new Service Panel</div>
                        </div>

                        <div class="card-body">
                          <div class="form-group">
                              <label class="col-form-label" for="panel_title">Title</label>
                              <input class="form-control" type="text" id="panel_title" name="panel_title" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="panel_txt">Text</label>
                              <textarea class="form-control summernote" type="text" id="panel_txt" name="panel_txt" required></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="panel_btn_txt">Text for Action Button</label>
                              <input class="form-control" type="text" id="panel_btn_txt" name="panel_btn_txt">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="panel_btn_link">Link for Action Button</label>
                              <input class="form-control" type="text" id="panel_btn_link" name="panel_btn_link">
                           </div>
                           
                           <div class="form-group">
                            <label class="col-form-label" for="sort_order"> Sort order</label>
                              <input type="number" class="form-control" id="sort_order" name="sort_order" value="12">
                              <span class="form-text"><small>Enter 0 if not to be displayed</small></span>
                          </div>
                          
                           <div class="form-group">
                              <label class="col-form-label">Background Image</label>
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
              keepHtml: false, // Remove all Html formats
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
            xmlhttp.open("GET","createperfstring.php?q="+str,true);
            xmlhttp.send();
        }
    }
  </script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
  <script>
  jQuery(document).ready(function($) {
    $( function() {
      $( "#datepicker" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
    });
    $( function() {
      $( "#datepicker2" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
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
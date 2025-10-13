<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Customers";

setAdminVars(0); // Dashboard section
$subtitle = "Customers";
$listurl = "customers.php";
$listname = "Customers";
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
$newphoto = isset($_POST['newphoto']) ? $_POST['newphoto'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row
  $stmt = $pdo->prepare("DELETE FROM cust_tbl WHERE cid = ? LIMIT 1");
  $stmt->execute([$which]); 
  $stmt->closeCursor();
}

if (($done == "done" || $newphoto == "newphoto") && ($admintype == 'AD' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $bus_name = isset($_POST['bus_name']) ? $_POST['bus_name'] : '';
  $vehicle = isset($_POST['vehicle']) ? $_POST['vehicle'] : '';
  $contact = isset($_POST['contact']) ? $_POST['contact'] : '';
  $email = isset($_POST['email']) ? $_POST['email'] : '';
  $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
  $address = isset($_POST['address']) ? $_POST['address'] : '';
  $town = isset($_POST['town']) ? $_POST['town'] : '';
  $zip = isset($_POST['zip']) ? $_POST['zip'] : '';
  $cust_txt = isset($_POST['cust_txt']) ? $_POST['cust_txt'] : '';
  $admin_txt = isset($_POST['admin_txt']) ? $_POST['admin_txt'] : '';

  // Update record
  $stmt = $pdo->prepare("UPDATE cust_tbl SET bus_name = ?, contact = ?, vehicle = ?, email = ?, phone = ?, address = ?, town = ?, zip = ?, cust_txt = ?, admin_txt = ?, who_by = ?, date_modified = ? WHERE cid = ? "); 
  $stmt->execute([$bus_name, $contact, $vehicle, $email, $phone, $address, $town, $zip, $cust_txt, $admin_txt, $usrkey, $today, $which]);
  $stmt->closeCursor();
}
?>
<?php
  // find the required record
$stmt = $pdo->prepare("SELECT custusr, bus_name, contact, vehicle, email, phone, address, town, zip, cust_txt, admin_txt, who_by, date_added, date_modified, last_used FROM cust_tbl WHERE cid = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$custusr = $row['custusr'];
$bus_name = $row['bus_name'];
$contact = $row['contact'];
$vehicle = $row['vehicle'];
$email = $row['email'];
$phone = $row['phone'];
$address = $row['address'];
$town = $row['town'];
$zip = $row['zip'];
$cust_txt = $row['cust_txt'];
$admin_txt = $row['admin_txt'];
$usrkey = $row['who_by'];
$date_added = $row['date_added'];
$date_modified = $row['date_modified'];
$last_used = $row['last_used'];
$stmt->closeCursor();
   $date_added = strtotime($date_added);
   $date_modified = strtotime($date_modified);
   $last_used = strtotime($last_used);
// whatever the record name is
  $changename = "$contact";
?>
<?php
// photo
if ($newphoto == "newphoto" && ($admintype == 'AD' || $admintype == 'DV')) {
   $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
   if (is_uploaded_file($_FILES['Image1']['tmp_name'])) {
    // create customer photo
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
   
   $large_image_path = '../custgallery/'.$generateID.'.jpg';
      $actualname = $generateID.'.jpg';
      $result = resize_save_jpeg( $temp_image_path, $large_image_path, 1800, 1800 );
   $isanimage = 0;
   if ( $result )
   {
      // find last sort order, add 4 and create new record
   $stmt = $pdo->prepare("SELECT sort_order FROM customer_gallery WHERE custusr = ? ORDER BY sort_order DESC LIMIT 1");
   $stmt->execute([$custusr]);
   $$value0 = 0; // Default value for sort_order
$subtitle = "stmt->fetchColumn();
   $stmt->closeCursor();
   $$value0 = 0; // Default value for sort_order
$subtitle = "sort_order + 4;

   $insert_stmt = $pdo->prepare("INSERT INTO customer_gallery (sort_order, filename, caption, date_created, custusr, who_by) VALUES (?, ?, ?, ?, ?, ?)");
   $insert_stmt->execute([$$value0 = 0; // Default value for sort_order
$subtitle = "usrkey]);
   $newid = $pdo->lastInsertId();
   $insert_stmt->closeCursor();
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>

setAdminVars(0); // Dashboard section            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                           <p>Created on: <?php echo date("D jS M Y", $date_added) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?>. Cusstomer last accessed on <?php echo date("D jS M Y", $last_used) ?></p>
                           <div class="form-group">
                              <label class="col-form-label" for="contact">Customer Name</label>
                              <input class="form-control" type="text" id="contact" name="contact" value="<?php echo $contact ?>" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="bus_name">Business Name</label>
                              <input class="form-control" type="text" id="bus_name" name="bus_name" value="<?php echo $bus_name ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="vehicle">Vehicle</label>
                              <input class="form-control" type="text" id="vehicle" name="vehicle" value="<?php echo $vehicle ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="phone">Tel</label>
                              <input class="form-control" type="text" id="phone" name="phone" value="<?php echo $phone ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="company_name">Email</label>
                              <input class="form-control" type="text" id="company_name" name="company_name" value="<?php echo $company_name ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="address">Address</label>
                              <textarea class="form-control" type="text" id="address" name="address"><?php echo $address ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="town">Town</label>
                              <input class="form-control" type="text" id="town" name="town" value="<?php echo $town ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="zip">Post Code</label>
                              <input class="form-control" type="text" id="zip" name="zip" value="<?php echo $zip ?>">
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="cust_txt">Displayed Text</label>
                              <textarea class="form-control summernote" type="text" id="cust_txt" name="cust_txt"><?php echo $cust_txt ?></textarea>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="admin_txt">Hidden Admin Text</label>
                              <textarea class="form-control" type="text" id="admin_txt" name="admin_txt"><?php echo $admin_txt ?></textarea>
</div>
                        
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
</div><!-- END card-->
                  </form>
               </div>
               <div class="col-xl-4">
                <div class="card border-info">
                  <div class="card-header bg-info">
                     <div class="card-title">Photos</div>
                  </div>
                  <div class="card-body">
                    <?php
                     // list photos
                     $tableset = $pdo->prepare("SELECT filename, caption, date_created FROM customer_gallery WHERE custusr = ? ORDER BY sort_order ASC");
                     $tableset->execute([$custusr]);
                     while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                        $filename = $row['filename'];
                        $caption = $row['caption'];
                        $date_created = $row['date_created'];
                        $date_created = strtotime($date_created);
                        echo "<p><em>$caption</em> <small>".date("d/m/Y", $date_created)."</small><br><img src=\"../custgallery/$filename\" alt=\"cust image\" width=\"200px\"></p>";
                     }
                     $tableset->closeCursor();
                    ?>

                  </div>
                  <div class="card-footer">
                     <form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                        <div class="form-group">
                           <label class="col-form-label">Add photo</label>
                           <input type="file" class="form-control" name="Image1" id="Image1">
                           <div class="form-group">
                              <label class="col-form-label" for="caption">Caption</label>
                              <textarea class="form-control" type="text" id="caption" name="caption"></textarea>
                           </div>
                           <div class="form-group">
                              <input type="hidden" name="newphoto" value="newphoto">
                              <input type="hidden" name="which" value="<?PHP echo $which ?>">
                              <div class="float-right"><button class="btn btn-purple" type="submit">Add Photo</button></div>
</div>
                     </form>
</div>
</div>

            <div class="row my-5">
               <div class="col-xl-8">
                     <!-- START card-->
                     <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                           <div class="card-title">Delete <?php echo $changename ?></div>
                        </div>
                        <div class="card-footer">
                           <div class="float-right">
                            <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" class="btn btn-labeled btn-danger" role="button"><span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
</div>
                     </div><!-- END card-->
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
   <script>
    $(document).ready(function (e) {
 $("#ProcessIcon").on('submit',(function(e) {
  e.preventDefault();
  $.ajax({
         url: "catphotoprocess.php",
   type: "POST",
   data:  new FormData(this),
   contentType: false,
         cache: false,
   processData:false,
   beforeSend : function()
   {
    $("#ProcessIconOutput").fadeOut();
    $("#err").fadeOut();
   },
   success: function(data)
      {
    if(data=='invalid')
    {
     // invalid file format.
     $("#err").html("Invalid File !").fadeIn();
    }
    else
    {
     // view uploaded file.
     $("#ProcessIconOutput").html(data).fadeIn();
 
    }
      },
     error: function(e) 
      {
    $("#err").html(e).fadeIn();
      }          
    });
 }));
});
</script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
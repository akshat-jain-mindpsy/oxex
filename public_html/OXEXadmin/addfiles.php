<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "File Manager";
$subtitle = "Add Files for inline content";
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
$done = isset($_POST['done']) ? $_POST['done'] : '';
$uploadmsg = '';
if ($done == 'done') {

// just adds file to 'partners' folder to use in HTML

define ("FILEREPOSITORY","../files/");
   if (is_uploaded_file($_FILES['ufile']['tmp_name'])) {
      $uploadtype = ($_FILES["ufile"]["type"]);
      $uploadname = $_FILES['ufile']['name'];
      if ((($_FILES["ufile"]["type"]) == "application/msword") || (($_FILES["ufile"]["type"]) == "application/wordprocessingml") || (($_FILES["ufile"]["type"]) == "application/pdf") || (($_FILES["ufile"]["type"]) == "image/gif") || (($_FILES["ufile"]["type"]) == "image/jpeg") || (($_FILES["ufile"]["type"]) == "image/png")) {
      echo "<p class=\"main\">$uploadtype</p>";
       $file_size=$_FILES['ufile']['size'];
       if ($_FILES["ufile"]["type"] == "application/msword") {
         $suffix = ".doc";
       }
       if ($_FILES["ufile"]["type"] == "application/pdf") {
         $suffix= ".pdf";
       }
       if ($_FILES["ufile"]["type"] == "application/wordprocessingml") {
         $suffix= ".docx";
       }
       if ($_FILES["ufile"]["type"] == "image/gif") {
         $suffix= ".gif";
       }
       if ($_FILES["ufile"]["type"] == "image/jpeg") {
         $suffix= ".jpg";
       }
       if ($_FILES["ufile"]["type"] == "image/png") {
         $suffix= ".png";
       }
       
         $result = move_uploaded_file($_FILES['ufile']['tmp_name'], FILEREPOSITORY."$uploadname");
         if ($result == 1) { 
          $uploadmsg = "<p>File successfully uploaded.</p>";
       }
         else { $uploadmsg = '<p><span class="btn btn-danger"> <i class="fa fa-times-circle"></i> There was a problem uploading the file.</span></p>';
       }
     
     } else {
     $uploadmsg = '<p><span class="btn btn-danger"> <i class="fa fa-times-circle"></i> Files must be uploaded in PDF or Word (.doc, .docx) formats or images (.gif, .jpg, .png).</span></p>';
      } #endIF
   } else {
   $uploadmsg = '<p><span class="btn btn-danger"> <i class="fa fa-times-circle"></i> upload failure</span></p>';
   }
};#end if $done
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?></small></div>
            </div>
            <div class="row">
               <div class="col-xl-8">
                  <div id="ProcessOutput">
                     <p><strong>Existing files</strong></p>
                     <?PHP
                     // file list for partners                       
                     $handle = opendir("../files");

                     while (($file = readdir($handle))!==false) {
                        // only list files that belong to this project, prefix of "$project."
                        if ($file != '.' && $file != '..') {
                           echo "<p>/files/$file</p>";
                        }
                     }
                     
                     closedir($handle);
                     ?>
                     <!-- space for Ajax result -->
                     </div>
               </div>
               <div class="col-xl-4">
                  <div class="card card-default">
                     <div class="card-header">Add Files</div>
                     <div class="card-body">
                        <form action="addfiles.php" method="post" id="ProcessForm" name="ProcessForm" class="form-horizontal" role="form" enctype="multipart/form-data">
                                 <?PHP
                        
                           // offer form to create an ID
                           echo '<div class="form-group">';
                              echo '<div class="col-lg-8">';
                                 echo '<input type="file" name="ufile" id="ufile" />';
                              echo '</div>';
                           echo '</div>';
                           echo '<div class="form-group">';
                              echo '<input name="-Nothing" type="submit" class="btn btn-success" value="Load" />';
                              echo "<input name=\"done\" type=\"hidden\"  value=\"done\" />";
                           echo '</div>';
                        ?>
                                </form>
                     </div>
                     <div class="card-footer">
                        <?php echo $uploadmsg ?>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
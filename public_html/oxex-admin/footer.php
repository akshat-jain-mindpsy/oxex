<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Page Content";

setAdminVars(0); // Dashboard section
$subtitle = "Footer content";
$listname = "Footer";
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

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
$which = isset($_GET['which']) ? $_GET['which'] : 0;
  $which = (int)$which;

if ($done == "done" && ($admintype == 'AD' || $admintype == 'DV')) {
  $footerl = isset($_POST['footerl']) ? $_POST['footerl'] : '';
  $footerr = isset($_POST['footerr']) ? $_POST['footerr'] : '';
  $footertm = isset($_POST['footertm']) ? $_POST['footerm'] : '';
  // Update record
  if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("UPDATE footer_tbl SET footerl = ?, footerr = ?, footerm = ? WHERE fid = ? "); 
    $stmt->execute([$footerl, $footerr, $footerm, $value1]);
  }
}
?>
<?php
  // find the required record
if ($usingSupabase) {
  $stmt = $supabase_pdo->prepare("SELECT footerl, footerr, footerm FROM footer_tbl WHERE fid = ?");
  $stmt->execute([$value1]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $footerl = $row ? $row['footerl'] : '';
  $footerr = $row ? $row['footerr'] : '';
  $footerm = $row ? $row['footerm'] : '';
} else {
  $footerl = '';
  $footerr = '';
  $footerm = '';
}
// whatever the record name is
  $changename = " the footer text";
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> </small></div>
            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                           
                          <div class="form-group">
                            <label for="footerl" class="col-form-label">Left-hand Footer Column</label>
                              <textarea rows="6" class="summernote" name="footerl" id="footerl" class="form-control"><?php echo $footerl ?></textarea>
                          </div>
                          
                          <div class="form-group">
                            <label for="footertm" class="col-form-label">Central Footer Column</label>
                              <textarea rows="6" class="summernote" name="footerm" id="footerm" class="form-control"><?php echo $footerm ?></textarea>
                          </div>
                          <div class="form-group">
                            <label for="footerr" class="col-form-label">Right-hand Footer Column</label>
                              <textarea rows="6" class="summernote" name="footerr" id="footerr" class="form-control"><?php echo $footerr ?></textarea>
</div>
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
</div><!-- END card-->
                  </form>
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
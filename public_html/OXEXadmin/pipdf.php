<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Page Content";
$subtitle = "PI PDF Link";
$listname = "PI Link";
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

if ($done == "done" && ($admintype == 'AD' || $admintype == 'DV')) {
  $pdfurl = isset($_POST['pdfurl']) ? $_POST['pdfurl'] : '';
  // Update record
  $stmt = $mysqli->prepare("UPDATE pi_pdf SET pdfurl = ? WHERE pid = ? "); 
  $stmt->bind_param("sssi", $pdfurl, $footerr, $footertm, $value1);
  $stmt->execute();
  $stmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT pdfurl FROM pi_pdf WHERE pid = ?");
$stmt->bind_param("i", $value1);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($pdfurl);
$stmt->fetch();
$stmt->close();
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small></div>
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
                            <label for="pdfurl" class="col-form-label">PI PDF URL</label>
                              <textarea rows="6" name="pdfurl" id="pdfurl" class="form-control"><?php echo $pdfurl ?></textarea>
                              <span class="form-text"><small>The URL <strong>must</strong> include the https:// prefix</small></span>
                          </div>

                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
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
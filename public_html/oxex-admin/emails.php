<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Emails";
$subtitle = "Email Texts";
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
// list only; pages have to be manually created in db
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
               <div class="content-title"><?php echo $pagetitle ?> </div>
            </div>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                  <th class="sort-alpha" data-priority="1">Email Name</th>
                                  <th>Used on</th>
                                  <th>Email Text?</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$stmt = $mysqli->prepare("SELECT sei, used_on, email_title, email_body FROM site_emails_tbl");
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($sei, $used_on, $email_title,  $email_body);
  while ($stmt->fetch()){
    if ($email_body == '' ) {
      $email_body = '<span class="badge badge-info">No&nbsp;</span>';
    } else {
      $email_body = '<span class="badge badge-success">Yes</span>';
    }

?>
<tr>
   <td><a href="emaildetail.php?which=<?php echo $sei ?>"><?php echo $email_title ?></a></td>
   <td><?php echo $used_on ?></td>
   <td><?php echo $email_body ?></td>
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
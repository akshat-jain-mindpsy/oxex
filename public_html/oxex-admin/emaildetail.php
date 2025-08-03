<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Email Content";
$subtitle = "Email content";
$listurl = "emails.php"; # where the delete script is found
$listname = "Emails";
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

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $email_title = isset($_POST['email_title']) ? $_POST['email_title'] : '';
  $email_subject = isset($_POST['email_subject']) ? $_POST['email_subject'] : '';
  $email_body = isset($_POST['email_body']) ? $_POST['email_body'] : '';
  $email_body2 = isset($_POST['email_body2']) ? $_POST['email_body2'] : '';
  $email_body3 = isset($_POST['email_body3']) ? $_POST['email_body3'] : '';
  $used_on = isset($_POST['used_on']) ? $_POST['used_on'] : '';
  $page_txt12 = isset($_POST['page_txt12']) ? $_POST['page_txt12'] : '';

  // Update record
  $stmt = $mysqli->prepare("UPDATE site_emails_tbl SET email_title = ?, email_subject = ?, email_body = ?, email_body2 = ?, email_body3 = ?, used_on = ? WHERE sei = ? "); 
  $stmt->bind_param("ssssssi", $email_title, $email_subject, $email_body, $email_body2, $email_body3, $used_on, $which);
  $stmt->execute();
  $stmt->close();
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT email_title, email_subject, email_body, email_body2, email_body3, used_on FROM site_emails_tbl WHERE sei = ?");
$stmt->bind_param("i", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($email_title, $email_subject, $email_body, $email_body2, $email_body3, $used_on);
$stmt->fetch();
$stmt->close();
  $date_added = strtotime($date_added);
// whatever the record name is
  $changename = " this email";
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
                              <label class="col-form-label" for="email_title">Email Name</label>
                              <input type="text" class="form-control" name="email_title" value="<?php echo $email_title ?>" required>
                            </div>
                           <div class="form-group">
                            <label class="col-form-label" for="used_on">Used for</label>
                            <input type="text" class="form-control" name="used_on" value="<?php echo $used_on ?>" required>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="email_subject">Email Subject</label>
                              <input type="text" class="form-control" name="email_subject" value="<?php echo $email_subject ?>" required>
                            </div>

                          <div class="form-group">
                            <label class="col-form-label">Body Text #1</label>
                              <textarea name="email_body" rows="5" id="email_body" class="form-control required"><?php echo $email_body ?></textarea>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Body Text #2</label>
                              <textarea name="email_body2" rows="5" id="email_body2" class="form-control"><?php echo $email_body2 ?></textarea>
                              <span class="form-text">The code [TODAY] used in the email will be replaced in the email by today's date.</span>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">Body Text #3</label>
                              <textarea name="email_body3" rows="5" id="email_body3" class="form-control"><?php echo $email_body3 ?></textarea>
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
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
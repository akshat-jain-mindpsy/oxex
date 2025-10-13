<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Admin";

$subtitle = "Admin users";
if(login_check($pdo) == true && ($admintype == 'AT'|| $admintype == 'DV')) {
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
   <style>
    .field-icon {
  float: right;
  margin-left: -25px;
  margin-top: -25px;
  position: relative;
  z-index: 2;
}
</style>
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
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if ($pdo) {
    $stmt = $pdo->prepare("DELETE FROM who_there WHERE whid = ? LIMIT 1");
    $stmt->execute([$which]);
    if ($stmt->rowCount() > 0) {
      // show message when deleting, not refreshing
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
    }
  }
}
?>
<?PHP
if ($newadmin == 'newadmin') {
  // The hashed password from the form
  $email = isset($_POST['e']) ? $_POST['e'] : '';
  $realname = isset($_POST['n']) ? $_POST['n'] : '';
  $password = isset($_POST['p']) ? $_POST['p'] : '';
  $accesslevel = isset($_POST['accesslevel']) ? $_POST['accesslevel'] : '';
  
  // Create a random salt
  $random_salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
  // Create salted password (Careful not to over season)
  $password = hash('sha512', $password.$random_salt);
  
  // Set access levels
  // DV = Developer (hidden from others but with all powers)
  // AT = AdminTop (top level with all powers)
  // AO = AdminOx (can only administer Oxford Supervisors & Trainees)
  // AE = AdminEx (can only administer Exeter Supervisors & Trainees)
  // AS = AdminSup (can only administer trainees assigned to them)
  $admintype = $accesslevel;
  if ($accesslevel == 'AT' || $accesslevel == 'AO' || $accesslevel == 'AE') {
     $ipaddress = 0;
     $isdev = 0;
     $photo = 'incredible.jpg';
  }
  if ($accesslevel == 'SO' || $accesslevel == 'SE') {
     $ipaddress = 0;
     $isdev = 0;
     $photo = 'bee.jpg';
  }
  /*
  if ($accesslevel == 'DV') {
    // Developer
   $ipaddress = 0;
   $admintype = 'DV';
   $isdev = 1;
   $photo = 'wizard.jpg';
  }
  */

  // create a usrkey for them
  $pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
  if (!$pdo) {
    echo "<p>Error: No database connection available.</p>";
    exit();
  }
  
  $numids = 1;
  while (!$numids == 0) {
    // make 32 digit hex string
    $usrkey = substr(md5(rand()), 0, 32);   
    $stmt = $pdo->prepare("SELECT whid FROM who_there WHERE usrkey = ? LIMIT 1");
    $stmt->execute([$usrkey]);
    $numids = $stmt->rowCount();
  } 
  $today = date("Ymd");
  
  // write new user record
  $insert_stmt = $pdo->prepare("INSERT INTO who_there (password, salt, email, realname, usrkey, admintype, startdate, isdev, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->execute([$password, $random_salt, $email, $realname, $usrkey, $admintype, $today, $isdev, $photo]);

  //echo "<p>Admin created for $realname, $email $usrkey</p>";
  //printf("[%d] %s\n", $insert_stmt->errno, $insert_stmt->error);
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
                                  <th class="sort-alpha" data-priority="1">Contact</th>
                                  <th class="sort-alpha" data-priority="2">Level</th>
                                  <th>email</th>
                                  <th>Last Login</th>
                                  <th>Click to delete</th>
                              </tr>
                           </thead>
                           <tbody>
<?PHP
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
  $tableset = $pdo->prepare("SELECT whid, realname, email, admintype, lastlogin FROM who_there ");
  $tableset->execute();
  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)) {
    $whid = $row['whid'];
    $realname = $row['realname'];
    $email = $row['email'];
    $table_admintype = $row['admintype'];
    $lastlogin = $row['lastlogin'];   

   if ($table_admintype == "AT") {
    $adminDesc = "Full Admin Control";
   }
   if ($table_admintype == "AO") {
    $adminDesc = "Oxford Course Tutor";
   }
   if ($table_admintype == "AE") {
    $adminDesc = "Exeter Course Tutor";
   }
   if ($table_admintype == "SO") {
    $adminDesc = "Oxford Supervisor";
   }
   if ($table_admintype == "SE") {
    $adminDesc = "Exeter Supervisor";
   }
   if ($table_admintype == "DV") {
    $adminDesc = "Developer";
   }
   if ($table_admintype != "DV") {
    // hide developer accounts from clients types
?>
<tr>
   <td><a href="adminusersdetail.php?which=<?php echo $whid ?>"><?php echo $realname ?></a></td>
   <td><?php echo $adminDesc ?></td>
   <td><?php echo $email ?></td>
   <td>
      <?php
      if ($lastlogin != '') {
         echo date("j M Y", $lastlogin);
      } else {
         echo "n/a";
      }
      ?>
         
   </td>
   <td><a href="adminusers.php?which=<?php echo $whid ?>&amp;del=del"><span class="badge badge-danger" title="Delete"><i class="fa-2x fas fa-minus"></i></span></a></td>
</tr>
 <?php
}
  }
}
?>
                           </tbody>
                        </table>
</div>
            </div><!-- end table row -->

            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Admin User</div>
                        </div>

                        <div class="card-body">
                          <div class="form-group">
                          <label class="col-form-label" for="name">Name</label>
                            <input type="text" class="form-control" id="name" name="n" required>
                        </div>
                        <!-- Email -->
                        <div class="form-group">
                          <label class="col-form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="e" required>
                        </div>   
                        <!-- Password -->
                        <div class="form-group">
                          <label class="col-form-label" for="password">Password</label>
                            <input type="password" class="form-control" id="inputPassword" name="p"><span toggle="#inputPassword" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                        </div>
                        <!-- Select box -->
                        <div class="form-group">
                          <label class="control-label col-lg-3">Admin Type</label>
                            <select class="form-control" id="accesslevel" name="accesslevel" required>
                              <?php

                                 // has superpowers to create anybody
                                 echo "<option value=\"AT\">Full Admin Control</option>";

                                 // has superpowers to create Oxford Types
                                 echo "<option value=\"AO\">Oxford Course Tutor</option>";
                                 echo "<option value=\"SO\">Oxford Supervisor</option>";

                                 // has superpowers to create Exeter Types
                                 echo "<option value=\"AE\">Exeter Course Tutor</option>";
                                 echo "<option value=\"SE\">Exeter Supervisor</option>";
                              
                              ?>
                           </select> 
                          <input type="hidden" name="newadmin" value="newadmin">
                          <span class="form-text">Passwords are 'hashed' and cannot be extracted from the database. Make a note of the password you're creating now ready to send to the new administrator. This page does *not* email login details to new users.</span>
</div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
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
     $('#fa').summernote({
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
     $(".toggle-password").click(function() {

      $(this).toggleClass("fa-eye fa-eye-slash");
      var input = $($(this).attr("toggle"));
      if (input.attr("type") == "password") {
        input.attr("type", "text");
      } else {
        input.attr("type", "password");
      }
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
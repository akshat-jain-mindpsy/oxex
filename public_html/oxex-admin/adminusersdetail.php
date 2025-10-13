<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Admin";

$subtitle = "Admin users";
$listurl = "adminusers.php"; # where the delete script is found
$listname = "Admin Users";
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV')) {
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
$changepw = isset($_POST['changepw']) ? $_POST['changepw'] : '';
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
    $realname = isset($_POST['realname']) ? $_POST['realname'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $accesslevel = isset($_POST['accesslevel']) ? $_POST['accesslevel'] : 'AM';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
  
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
  if ($accesslevel == 'DV') {
     $ipaddress = 0;
     $admintype = 'DV';
     $isdev = 1;
     $photo = 'wizard.jpg';
  }
  // Update record
  if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("UPDATE who_there SET realname = ?, email = ?, admintype = ?, isdev = ?, photo = ? WHERE whid = ?"); 
    $stmt->execute([$realname, $email, $accesslevel, $isdev, $photo, $which]);
  }

  // if changing password create salt and secure pw and update
    $pwmsg = '';
    if ($changepw == 'change' && $password != '') {
      // Create a random salt
    $random_salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
    // Create salted password (Careful not to over season)
    $password = hash('sha512', $password.$random_salt);
      if ($usingSupabase) {
        $stmt = $supabase_pdo->prepare("UPDATE who_there SET password = ?, salt = ?, realname = ?, email = ?, accesslevel = ?, isdev = ?, photo = ? WHERE whid = ?");
        $stmt->execute([$password, $random_salt, $realname, $email, $pageadmintype, $isdev, $photo, $which]);
      }
      $pwmsg = "<p>Password changed</p>";
    }
}
?>
<?php
  // find the required record
if ($usingSupabase) {
  $stmt = $supabase_pdo->prepare("SELECT realname, email, admintype, startdate, usrkey, lastlogin FROM who_there WHERE whid = ?");
  $stmt->execute([$which]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $adrealname = $row ? $row['realname'] : '';
  $email = $row ? $row['email'] : '';
  $pageadmintype = $row ? $row['admintype'] : '';
  $startdate = $row ? $row['startdate'] : '';
  $usrkey = $row ? $row['usrkey'] : '';
  $lastlogin = $row ? $row['lastlogin'] : '';
} else {
  $adrealname = '';
  $email = '';
  $pageadmintype = '';
  $startdate = '';
  $usrkey = '';
  $lastlogin = '';
}
// whatever the record name is
  $changename = " this Admin User";
  $startdate = strtotime($startdate);
  $lastlogin = strtotime($lastlogin);
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
                              <label class="col-form-label" for="realname">Name</label>
                              <input class="form-control" type="text" id="realname" name="realname" value="<?php echo $adrealname ?>" required>
                              <span class="form-text">Created <?php echo date('d/m/Y',$startdate) ?>. Last Login <?php echo date('d/m/Y',$lastlogin) ?></span>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="accesslevel"> Admin Type</label>
                                <select name="accesslevel" class="form-control">
                                  <?php

                                 // has superpowers to create anybody
                                 echo "<option value=\"AT\"";
                                 if ($pageadmintype == "AT") {
                                    echo " selected";
                                 }
                                 echo ">Full Admin Control</option>";
                                 echo "<option value=\"AO\"";
                                 if ($pageadmintype == "AO") {
                                    echo " selected";
                                 }
                                 echo ">Oxford Course Tutor</option>";
                                 echo "<option value=\"SO\"";
                                 if ($pageadmintype == "SO") {
                                    echo " selected";
                                 }
                                 echo ">Oxford Supervisor</option>";
                                 echo "<option value=\"AE\"";
                                 if ($pageadmintype == "AE") {
                                    echo " selected";
                                 }
                                 echo ">Exeter Course Tutor</option>";
                                 echo "<option value=\"SE\"";
                                 if ($pageadmintype == "SE") {
                                    echo " selected";
                                 }
                                 echo ">Exeter Supervisor</option>";
                                 
                              
                              ?>
                                </select>
                            </div>
                
                          <div class="form-group">
                            <label class="col-form-label" for="email">Email</label>
                              <input type="email" class="form-control" id="email" name="email" value="<?php echo $email ?>" required>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label">&nbsp;</label>
                              <label class="checkbox-inline">
                                <input type="checkbox" name="changepw" value="change">
                                Tick box to allow change of password</label>
                          </div>
                          <div class="form-group">
                            <label class="col-form-label" for="password">New Password</label>
                              <input type="password" class="form-control" id="inputPassword" name="p"><span toggle="#inputPassword" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                        </div><!-- END card footer -->
                  </form>
                  
                  </div>
                  
               </div>
               <!-- START delete card placed just below amend card, outside the form -->
               <div class="card border-danger mt-4">
                     <div class="card-header bg-danger text-white">
                        <div class="card-title">Delete <?php echo $changename ?></div>
                     </div>
                     <div class="card-footer">
                        <div class="float-right">
                           <a href="<?php echo $listurl ?>?del=del&amp;which=<?php echo $which ?>" class="btn btn-labeled btn-danger" role="button" onclick="return confirm('Are you sure you want to delete this Admin User?')"><span class="btn-label"><i class="fa fa-times"></i></span>Delete now!</a>
                        </div>
                     </div><!-- END delete card -->
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
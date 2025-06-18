<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/p_functions.php';
$value0 = 0;
$value1 = 1;
$value2 = 2;
$value3 = 3;
$valueblank = '';
$err_msg = '';
$today = date("YmjHi");#YYYYMMDDHHSS
$timestamp = time();
$expired = strtotime('+24 hour', $timestamp);
$valid_attampt = 1;
$valid_password  = $_POST['password'];
$valid_confirm_password  = $_POST['confirm_password'];
$usrkey = $_POST['who'];

// just check there is such a user
$stmt = $mysqli->prepare("SELECT whid FROM who_there WHERE usrkey = ? LIMIT 1");
$stmt->bind_param('s', $usrkey);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($tid);
$stmt->fetch();
$numrows = $stmt->num_rows;
$stmt->close();

$page_txt2 = "Your password has been changed. Please note all attempts to change passwords are recorded and reviewed by the administrators for this website.";
$page_txt4 = "Invalid. Please start again.";
$page_txt5 = "Your password entries didn't match so we don't know which is correct.";

if ($numrows == 0) {
  $valid_attampt = 0;
  $err_msg = $err_msg.$page_txt4;
}

if ($valid_password != $valid_confirm_password) {
  $valid_attampt = 0;
  $err_msg = $err_msg.$page_txt5;
}

if ($valid_attampt == 1) {
  // ok, write the neww hashed password and delete the old text one
  $cancel = 0;
  $random_salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
  $user_password = hash('sha512', $valid_password.$random_salt);
  
  // set record to say it's now been used, remove old text pw
  $stmt = $mysqli->prepare("UPDATE who_there SET password = ?, salt = ? WHERE usrkey = ?"); 
  $stmt->bind_param("sss", $user_password, $random_salt, $usrkey);
  $stmt->execute();
  $stmt->close();
}

?><!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title>Admin</title><!-- =============== VENDOR STYLES ===============-->
   <!-- FONT AWESOME-->
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/brands.css">
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/regular.css">
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/solid.css">
   <link rel="stylesheet" href="assets/vendor/@fortawesome/fontawesome-free/css/fontawesome.css"><!-- ANIMATE.CSS-->
   <link rel="stylesheet" href="assets/vendor/animate.css/animate.css"><!-- =============== BOOTSTRAP STYLES ===============-->
   <link rel="stylesheet" href="assets/css/bootstrap.css" id="bscss"><!-- =============== APP STYLES ===============-->
   <link rel="stylesheet" href="assets/css/app.css" id="maincss">
</head>

<body>
   <div class="wrapper">
      <div class="full-page-background bg-darker"></div>
      <div class="d-flex align-items-center justify-content-center h-100 w-100 flex-column">
         <!-- START card-->
         <div class="card card-flat" style="min-width: 300px">
            <div class="card-header text-center bg-transparent border-0">
               <img src="assets/img/Oxford_Health_correct_logo.jfif" alt="NHS logo" class="img-fluid">
               <h3 class="py-3">OXICPTR Admin</h3></div>
            <div class="card-body">
               <h3>SIGN IN TO CONTINUE</h3>
            <?php
            if ($err_msg != '') {
              echo "<p><strong>$err_msg</strong></p>";
            }
            ?>
            <form method="post" action="login2.php" class="py-3 pz-3">
              <div class="form-group">
                     <div class="input-group with-focus"><input class="form-control border-right-0" id="email" name="email" type="email" placeholder="Enter email" autocomplete="off" required>
                        <div class="input-group-append"><span class="input-group-text text-muted bg-transparent border-left-0"><em class="fa fa-envelope"></em></span></div>
                     </div>
                  </div>
                  <div class="form-group">
                     <div class="input-group with-focus"><input class="form-control border-right-0" id="p" name="p" type="password" placeholder="Password" required>
                        <div class="input-group-append"><span class="input-group-text text-muted bg-transparent border-left-0"><em class="fa fa-lock"></em></span></div>
                     </div>
                  </div>
                  <div class="clearfix">
                     
                  </div><button class="btn btn-block btn-primary my-3" type="submit">Login</button>
            </form>
            <p class="my-3"><a href="reset.php">Reset/Change Password</a></p>
         </div>
      </div>
   </div><!-- =============== VENDOR SCRIPTS ===============-->
   <!-- STORAGE API-->
   <script src="assets/vendor/js-storage/js.storage.js"></script><!-- i18next-->
   <script src="assets/vendor/i18next/i18next.js"></script>
   <script src="assets/vendor/i18next-xhr-backend/i18nextXHRBackend.js"></script><!-- JQUERY-->
   <script src="assets/vendor/jquery/dist/jquery.js"></script><!-- BOOTSTRAP-->
   <script src="assets/vendor/popper.js/dist/umd/popper.js"></script>
   <script src="assets/vendor/bootstrap/dist/js/bootstrap.js"></script><!-- PARSLEY-->
   <script src="assets/vendor/parsleyjs/dist/parsley.js"></script><!-- =============== APP SCRIPTS ===============-->
   <script src="assets/js/app.js"></script>
</body>

</html>
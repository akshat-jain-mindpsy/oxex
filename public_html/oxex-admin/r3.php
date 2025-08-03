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
$token  = $mysqli->real_escape_string($_GET['t']);
//$reset_salt included
$token_check = hash('sha512', $token.$reset_salt);

$stmt = $mysqli->prepare("SELECT usrkey, timesent, timeexp, invalidated FROM reset_tbl WHERE tokenhash = ? LIMIT 1");
$stmt->bind_param('s', $token_check);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($usrkey, $timesent, $timeexp, $invalidated);
$stmt->fetch();
$numrows = $stmt->num_rows;
$stmt->close();
$page_txt4 = "Reset links can only be used once. You will need to apply again to reset your password";
$page_txt5 = "That reset link is invalid. You will need to apply again to reset your password. ";
$page_txt6 = "That reset link has timed-out. You will need to apply again to reset your password.";
if ($invalidated == 0) {
  // token already invalidated
  $valid_attampt = 0;
  $err_msg = $err_msg.$page_txt4;
}
if ($numrows != 1) {
  // not found
  $valid_attampt = 0;
  $err_msg = $err_msg.$page_txt5;
}
if ($timeexp > $expired) {
  // too late
  $valid_attampt = 0;
  $err_msg = $err_msg.$page_txt6;
}
if ($valid_attampt == 1) {
  // ok
  $cancel = 0;
  $err_msg = $err_msg.' ';
  // set record to say it's now been used
  $stmt = $mysqli->prepare("UPDATE reset_tbl SET invalidated = ? WHERE usrkey = ?"); 
  $stmt->bind_param("is", $cancel, $usrkey);
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
               <h3>Reset your password</h3>
            <?php
            echo "<p><strong>$err_msg</strong></p>\n";
            if ($valid_attampt == 0) {
            ?>
            <form method="post" action="reset2.php" class="py-3 pz-3">
              <div class="form-group">
                <label for="email" class="sr-only">Email address</label>
                <input type="email" class="form-control" name="email" id="email" aria-describedby="emailHelp" placeholder="Email address">
              </div>
                <input type="hidden" name="done" value="done">
                <button type="submit" class="btn btn-primary">Reset password</button>
            </form>
            <?PHP
            } else {
            ?>
            <?php echo "<p>$page_txt4</p>" ?>
            <form method="post" action="reset4.php" class="py-3 pz-3">
              <div class="form-group">
                <label for="password" class="sr-only">Password</label>
                <input type="password" class="form-control" id="password" name="password"  placeholder="Password">
              </div>
              <div class="form-group">
                <label for="confirm_password" class="sr-only">Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password"  placeholder="Repeat Password">
              </div>
                <input type="hidden" name="done" value="done">
                <input name="who" type="hidden" id="who" value="<?php echo $usrkey ?>">
                <button type="submit" class="btn btn-primary">Reset password</button>
            </form>
            <?php
            }
            ?>
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
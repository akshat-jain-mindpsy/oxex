<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
include 'incl/sess.php';
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

// Check if this is an admin reset token by looking up the usrkey in who_there table
$is_admin_token = false;
if ($numrows == 1 && $usrkey) {
  $admin_check = $mysqli->prepare("SELECT whid FROM who_there WHERE usrkey = ? LIMIT 1");
  $admin_check->bind_param('s', $usrkey);
  $admin_check->execute();
  $admin_check->store_result();
  $admin_check->bind_result($whid);
  $admin_check->fetch();
  $is_admin_token = ($admin_check->num_rows == 1);
  $admin_check->close();
  
  // If this is an admin token, redirect to admin reset page
  if ($is_admin_token) {
    header("Location: https://www.oxex.co.uk/oxex-admin/r3.php?t=" . urlencode($_GET['t']));
    exit();
  }
}

// Define error message texts
$page_txt4 = "Reset links can only be used once. You will need to apply again to reset your password";
$page_txt5 = "That reset link is invalid. You will need to apply again to reset your password. ";
$page_txt6 = "That reset link has timed-out. You will need to apply again to reset your password.";

// error message texts, remove html
$page_txt4 = str_replace("<p>", " ", $page_txt4);
$page_txt4 = str_replace("</p>", " ", $page_txt4);
$page_txt5 = str_replace("<p>", " ", $page_txt5);
$page_txt5 = str_replace("</p>", " ", $page_txt5);
$page_txt6 = str_replace("<p>", " ", $page_txt6);
$page_txt6 = str_replace("</p>", " ", $page_txt6);

if ($numrows != 1) {
  // not found
  $valid_attampt = 0;
  $err_msg = $page_txt5;
} elseif ($invalidated == 1) {
  // token already invalidated
  $valid_attampt = 0;
  $err_msg = $page_txt4;
} elseif ($timestamp > $timeexp) {
  // too late
  $valid_attampt = 0;
  $err_msg = $page_txt6;
}
if ($valid_attampt == 1) {
  // ok
  $used = 1;
  $err_msg = $err_msg.' ';
  // set record to say it's now been used
  $stmt = $mysqli->prepare("UPDATE reset_tbl SET invalidated = ? WHERE usrkey = ?"); 
  $stmt->bind_param("is", $used, $usrkey);
  $stmt->execute();
  $stmt->close();
}
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $googleTitle ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>  
  </head>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <div class="container-fluid py-5">
      <div class="container">
        <div class="row">
          <div class="col">
            <?php
            if ($page_title != '') {
              echo "<h1>$page_title</h1>";
            }
            ?>
          </div>
        </div>
      </div>
      <div class="container-md my-5 ">
        <div class="row">
          <div class="col-sm-6 bg-nhsuk-grey-3">
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
                <button type="submit" class="btn btn-nhs">Reset password</button>
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
                <button type="submit" class="btn btn-nhs">Reset password</button>
            </form>
            <?php
            }
            ?>
          </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php include 'incl/js.php' ?>
  </body>
</html>
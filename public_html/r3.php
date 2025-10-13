<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
include 'incl/sess.php';
$err_msg = '';
$today = date("YmjHi");#YYYYMMDDHHSS
$timestamp = time();
$expired = strtotime('+24 hour', $timestamp);
$valid_attampt = 1;
$token = $_GET['t'];
//$reset_salt included
$token_check = hash('sha512', $token.$reset_salt);

$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("SELECT usrkey, timesent, timeexp, invalidated FROM reset_tbl WHERE tokenhash = ? LIMIT 1");
    $stmt->execute([$token_check]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $usrkey = $row ? $row['usrkey'] : null;
    $timesent = $row ? $row['timesent'] : null;
    $timeexp = $row ? $row['timeexp'] : null;
    $invalidated = $row ? $row['invalidated'] : null;
    $numrows = $row ? 1 : 0;
} else {
    $usrkey = null;
    $timesent = null;
    $timeexp = null;
    $invalidated = null;
    $numrows = 0;
}

// Check if this is an admin reset token by looking up the usrkey in who_there table
$is_admin_token = false;
if ($numrows == 1 && $usrkey) {
  if ($usingSupabase) {
    $admin_check = $supabase_pdo->prepare("SELECT whid FROM who_there WHERE usrkey = ? LIMIT 1");
    $admin_check->execute([$usrkey]);
    $admin_row = $admin_check->fetch(PDO::FETCH_ASSOC);
    $is_admin_token = ($admin_row !== false);
  }
  
  // If this is an admin token, redirect to admin reset page
  if ($is_admin_token) {
    // Redirect to admin reset using ADMIN_BASE_URL or BASE_URL fallback
    $adminBase = getenv('ADMIN_BASE_URL');
    if (!$adminBase) {
      $adminBase = rtrim(getenv('BASE_URL') ?: 'https://www.oxex.co.uk', '/').'/oxex-admin';
    } else {
      $adminBase = rtrim($adminBase, '/');
    }
    header('Location: ' . $adminBase . '/r3.php?t=' . urlencode($_GET['t']));
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
  if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("UPDATE reset_tbl SET invalidated = ? WHERE usrkey = ?"); 
    $stmt->execute([$used, $usrkey]);
  }
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
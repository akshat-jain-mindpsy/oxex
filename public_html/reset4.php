<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
include 'incl/sess.php';
$err_msg = '';
$today = date("YmjHi");#YYYYMMDDHHSS
$timestamp = time();
$expired = strtotime('+24 hour', $timestamp);
$valid_attampt = 1;
$valid_password  = $_POST['password'];
$valid_confirm_password  = $_POST['confirm_password'];
$trainkey = $_POST['who'];

// just check there is such a user
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("SELECT tid FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
    $stmt->execute([$trainkey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $tid = $row ? (int)$row['tid'] : 0;
    $numrows = $row ? 1 : 0;
} else {
    $tid = 0;
    $numrows = 0;
}

// error message texts, remove html
$page_txt4 = str_replace("<p>", " ", $page_txt4);
$page_txt4 = str_replace("</p>", " ", $page_txt4);
$page_txt5 = str_replace("<p>", " ", $page_txt5);
$page_txt5 = str_replace("</p>", " ", $page_txt5);

//echo "<title>Login_reset  $trainkey </title>\n";
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
  if ($usingSupabase) {
    $stmt = $supabase_pdo->prepare("UPDATE trainee_tbl SET password = ?, salt = ?, txtpw = ? WHERE trainkey = ?"); 
    $stmt->execute([$user_password, $random_salt, $valueblank, $trainkey]);
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
            if ($err_msg != '') {
              echo "<p><strong>$err_msg</strong></p>";
            }
            ?>
            <form method="post" action="login2.php" class="py-3 pz-3">
              <div class="form-group">
                <label for="ee" class="sr-only">Email address</label>
                <input type="email" class="form-control" name="ee" id="ee" aria-describedby="emailHelp" placeholder="Email address">
              </div>
              <div class="form-group">
                <label for="pp" class="sr-only">Password</label>
                <input type="password" class="form-control" id="pp" name="pp"  placeholder="Password">
              </div>
                <input type="hidden" name="done" value="done">
                <button type="submit" class="btn btn-nhs">Log in</button>
            </form>
            <p class="my-3"><a href="reset.php">Forgotten your password?</a></p>
          </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php include 'incl/js.php' ?>
  </body>
</html>
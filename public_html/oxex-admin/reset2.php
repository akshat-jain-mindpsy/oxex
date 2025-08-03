<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/p_functions.php';
$value0 = 0;
$value1 = 1;
$value2 = 2;
$value3 = 3;
$valueblank = '';
$validform = 1;
$today = date("H:i j/m/Y");#
$timestamp = time();
$expired = strtotime('+24 hour', $timestamp);
$invalidated = 1;
$email = isset($_POST['email']) ? $_POST['email'] : '';
$err_msg = '';
$tid = 0;
// check valid email
if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
  $valid_email = strtolower($email);
  $stmt = $mysqli->prepare("SELECT whid, usrkey FROM who_there WHERE email = ? ");
  $stmt->bind_param("s", $valid_email);
  $stmt->bind_result($whid, $usrkey);
  $stmt->execute();
  $stmt->fetch();
  $numrows = $stmt->num_rows;
  $stmt->close();
} else {
   $page_txt4 = "<p>That isn't a valid email address.</p>";
  $valid_email = "no";
  $validform = 0;
  $err_msg = $err_msg.$page_txt4;
}
$page_txt5 = "<p>We cannot fulfil your request at the moment.</p>";
if ($whid < 1) {
  $valid_email="no";
  $validform = 0;
  $err_msg = $err_msg.$page_txt5;
}

if ($valid_email != "no") {
  // create reset info and email user
  $token = substr(md5(rand()), 0, 32);
  $hashed_salt = '93b97938d3249309c9f30a25318671e911d0d5f6c06820fc0ee9d92fc3bb1f19fedf55735142864a93ff4e5c128c41c4c613728cf247cc2eab1ecad9e4402448';
  $hashed_token = hash('sha512', $token.$hashed_salt);
  $salt = '';
  
  // write to reset table
  try {
    $stmt = $mysqli->prepare("INSERT INTO reset_tbl (usrkey, timesent, timeexp, req_date, tokenhash, tokensalt, invalidated) VALUES (?, ?, ?, ?, ?, ?, ? )");
    $stmt->bind_param("ssssssi", $usrkey, $timestamp, $expired, $today, $hashed_token, $salt, $invalidated );
    $stmt->execute();
    $stmt->close();
  } catch (mysqli_sql_exception $e) {
    error_log("Database insertion error: " . $e->getMessage());
    $err_msg = "An error occurred while processing your request. Please try again.";
  }
  
  // get email content
  $stmt = $mysqli->prepare("SELECT email_subject, email_body, email_body2, email_body3 FROM site_emails_tbl WHERE sei = ?");
  $stmt->bind_param("i", $value2);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($email_subject, $email_body, $email_body2, $email_body3);
  $stmt->fetch();
  $stmt->close();
  $link = 'https://www.oxex.co.uk/oxex-admin/r3.php?t='.$token;
  $email_body = str_replace("[LINK]", $link, $email_body);
  $email_body = str_replace("[TODAY]", $today, $email_body);
  $email_body2 = str_replace("[LINK]", $link, $email_body2);
  $email_body2 = str_replace("[TODAY]", $today, $email_body2);
  
  // email the user
  $subject = $email_subject;
  $feedback = "$email_body\n\n$link\n\n$email_body2";
  $message = "$feedback\n\n";
  $message .= "$email_body3\n\n";
  

  require '../OXEXfolder/PHPMailerAutoload.php';
  $MailHost = 'smtp.stackmail.com';
  $FromEmail = 'admin@oxex.co.uk';
  $frompassword = '3u(**zL€bWA[';
  //$BCCEmail = 'design@icatching.co.uk';

  $mail = new PHPMailer;
  $mail->CharSet = 'UTF-8';

  $page_txt9 = "<p>An email has been sent from admin@oxex.co.uk to&nbsp;<br></p>";
  $page_txt10 = "<p>Please note that the link in this email will expire in 24 hours.</p><p>If you are unable to click the link within this time then please request the password reset again at a more convenient time.<br></p>";

  //$mail->Debugoutput = 'html';
  $mail->isSMTP();
  $mail->Host = $MailHost;
  $mail->SMTPAuth = true;
  $mail->Username = $FromEmail;
  $mail->Password = $frompassword;
  $mail->Port = 587;
  $mail->setFrom($FromEmail, 'E-log admin  website');
  $mail->isHTML(false);
  $mail->AddAddress($valid_email);
  //$mail->AddBCC($BCCEmail);
  $mail->Subject = $subject;
  $mail->Body = $message;
  if(!$mail->send()) {
      $err_msg = 'We cannot fulfil your request at the moment. ';
      echo 'Mailer Error: ' . $mail->ErrorInfo;
  } else {
      $err_msg = $err_msg."$page_txt9 $valid_email. $page_txt10 ";
  }
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
            ?>
            <?php
            $page_txt2 = "<p>You have requested a reset of your password for this website.</p>";
            if ($valid_email != "no") {
              echo "<p>$page_txt2</p>";
            } else {
            ?>
            <form method="post" action="reset2.php" class="py-3 pz-3">
              <div class="form-group">
                <label for="email" class="sr-only">Email address</label>
                <input type="email" class="form-control" name="email" id="email" aria-describedby="emailHelp" placeholder="Email address">
              </div>
                <input type="hidden" name="done" value="done">
                <button type="submit" class="btn btn-primary">Reset password</button>
            </form>
            <?php
            }
            ?>
            </div>
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
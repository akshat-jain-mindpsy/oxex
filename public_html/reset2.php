<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
include 'incl/sess.php';
$validform = 1;
$today = date("H:i j/m/Y");#
$timestamp = time();
$expired = strtotime('+24 hour', $timestamp);
$invalidated = 0;
$email = isset($_POST['email']) ? $_POST['email'] : '';
$err_msg = '';
$tid = 0;
// error message texts, remove html

$page_txt4 = strip_tags($page_txt4);
$page_txt5 = strip_tags($page_txt5);
$page_txt9 = strip_tags($page_txt9);
$page_txt10 = strip_tags($page_txt10);
// check valid email
if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
  $valid_email = strtolower($email);
  $stmt = $mysqli->prepare("SELECT tid, trainkey FROM trainee_tbl WHERE email = ? ");
  $stmt->bind_param("s", $valid_email);
  $stmt->bind_result($tid, $trainkey);
  $stmt->execute();
  $stmt->fetch();
  $numrows = $stmt->num_rows;
  $stmt->close();
} else {
  $valid_email = "no";
  $validform = 0;
  $err_msg = $err_msg.$page_txt4;
}

if ($tid < 1) {
  $valid_email="no";
  $validform = 0;
  $err_msg = $err_msg.$page_txt5;
}


if ($valid_email != "no") {
  // create reset info and email user
  $token = substr(md5(rand()), 0, 32);
  $hashed_token = hash('sha512', $token.$reset_salt);
  $salt = '';
  
  // write to reset table
  $pid = 0; // Set pid to 0 as seen in existing records
  $insert_stmt = $mysqli->prepare("INSERT INTO reset_tbl (pid, usrkey, timesent, timeexp, req_date, tokenhash, tokensalt, invalidated) VALUES (?, ?, ?, ?, ?, ?, ?, ? )");
    $insert_stmt->bind_param("issssssi", $pid, $trainkey, $timestamp, $expired, $today, $hashed_token, $salt, $invalidated );
    $insert_stmt->execute();
    $insert_stmt->close();
  
  // get email content
  $stmt = $mysqli->prepare("SELECT email_subject, email_body, email_body2, email_body3 FROM site_emails_tbl WHERE sei = ?");
  $stmt->bind_param("i", $value1);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($email_subject, $email_body, $email_body2, $email_body3);
  $stmt->fetch();
  $stmt->close();
  $link = 'https://www.oxex.co.uk/r3.php?t='.$token;
  $email_body = str_replace("[LINK]", $link, $email_body);
  $email_body = str_replace("[TODAY]", $today, $email_body);
  $email_body2 = str_replace("[LINK]", $link, $email_body2);
  $email_body2 = str_replace("[TODAY]", $today, $email_body2);
  
  // email the user
  $subject = $email_subject;
  $feedback = "$email_body\n\n$link\n\n$email_body2";
  $message = "$feedback\n\n";
  $message .= "$email_body3\n\n";
  

  // Use secure mail handler with multiple fallback options
  require 'OXEXfolder/secure_mail.php';
  
  // Log email attempt
  error_log("=== PASSWORD RESET EMAIL ATTEMPT (SECURE) ===");
  error_log("Trainee email: $valid_email");
  error_log("Subject: $subject");
  error_log("Message length: " . strlen($message) . " characters");
  
  $mail = new SecureMail();
  
  if($mail->send($valid_email, $subject, $message, 'noreply@oxex.co.uk', 'E-log Website')) {
      error_log("✅ SECURE EMAIL PROCESSED SUCCESSFULLY for: $valid_email");
      $err_msg = $err_msg."$page_txt9 $valid_email. $page_txt10 ";
      $err_msg .= "<br><small style='color: green;'>✅ Email sent successfully! Check your inbox or contact admin if not received.</small>";
  } else {
      error_log("❌ SECURE EMAIL PROCESSING FAILED for: $valid_email");
      $err_msg = 'We cannot fulfil your request at the moment. ';
      $err_msg .= "<br><small style='color: red;'>❌ Email processing failed. Please try again later or contact support.</small>";
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
          <div class="col-sm-6 bg-nhsuk-grey-3 py-2">
            <?php
            echo "<p><strong>$err_msg</strong></p>\n";
            ?>
            <?php
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
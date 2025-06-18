<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
include 'incl/sess.php';
$validform = 1;
$today = date("H:i j/m/Y");#
$timestamp = time();
$expired = strtotime('+24 hour', $timestamp);
$invalidated = 1;
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
  $hashed_salt = '93b97938d3249309c9f30a25318671e911d0d5f6c06820fc0ee9d92fc3bb1f19fedf55735142864a93ff4e5c128c41c4c613728cf247cc2eab1ecad9e4402448';
  $hashed_token = hash('sha512', $token.$hashed_salt);
  $salt = '';
  
  // write to reset table
  $insert_stmt = $mysqli->prepare("INSERT INTO reset_tbl (usrkey, timesent, timeexp, req_date, tokenhash, tokensalt, invalidated) VALUES (?, ?, ?, ?, ?, ?, ? )");
    $insert_stmt->bind_param("ssssssi", $trainkey, $timestamp, $expired, $today, $hashed_token, $salt, $invalidated );
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
  

  require 'OXEXfolder/PHPMailerAutoload.php';
  $MailHost = 'smtp.stackmail.com';
  $FromEmail = 'admin@oxex.co.uk';
  $frompassword = '3u(**zL€bWA[';
  //$BCCEmail = 'design@icatching.co.uk';

  $mail = new PHPMailer;
  $mail->CharSet = 'UTF-8';

  //$mail->Debugoutput = 'html';
  $mail->isSMTP();
  $mail->Host = $MailHost;
  $mail->SMTPAuth = true;
  $mail->Username = $FromEmail;
  $mail->Password = $frompassword;
  $mail->Port = 587;
  $mail->setFrom($FromEmail, 'E-log Website');
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
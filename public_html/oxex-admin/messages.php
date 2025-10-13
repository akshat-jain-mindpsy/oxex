<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Messages";

setAdminVars(0); // Dashboard section
$subtitle = "Messages";
if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
</head>
<?php
// mark message as read
$msg = isset($_GET['msg']) ? $_GET['msg'] : 0;
   $msg = (int)$msg;
$del = isset($_GET['del']) ? $_GET['del'] : 0;
   $del = (int)$del;
$delalert = '';
if ($del == 1) {
  // mark as read if it's your message
   $stmt = $pdo->prepare("UPDATE admin_msg SET isread = ? WHERE amid = ? AND usrkey_to = ?"); 
   $stmt->execute([$value1, $msg, $usrkey]); 
   if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Message marked as read</strong></div></div></div>";
   }
   $stmt->closeCursor();
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <p>Your unread messages:</p>
                  <?php
                  $now = time();
                  // list  unread messages
                  if ($nummsg > 0) {
                     $tableset = $pdo->prepare("SELECT amid, usrkey_from, date_sent, msg FROM admin_msg WHERE usrkey_to = ? AND isread = ? ORDER BY date_sent DESC");
                     $tableset->execute([$usrkey, $value0]);
                     while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                        $amid = $row['amid'];
                        $usrkey_from = $row['usrkey_from'];
                        $date_sent = $row['date_sent'];
                        $msg = $row['msg'];
                        $msg_sm = substr($msg, 0, 80)."...";
                        // lookup sender
                        $stmt = $pdo->prepare("SELECT realname, isonline, lastlogin, photo FROM who_there WHERE usrkey = ?");
                        $stmt->execute([$usrkey_from]);
                        $sender_row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $mailername = $sender_row['realname'];
                        $isonline = $sender_row['isonline'];
                        $lastlogin = $sender_row['lastlogin'];
                        $senderphoto = $sender_row['photo'];
                        $stmt->closeCursor();
                        if ($mailername == '') {
                           $mailername = "assets/img/user/wizard.jpg";
                        } else {
                           $mailername = "assets/img/user/$senderphoto";
                        }
                        $date_sent = strtotime($date_sent);
                        echo "<div class=\"dropdown-item\">";
                        echo "   <div class=\"list-group\">";
                        echo "      <div class=\"list-group-item list-group-item-action\">";
                        echo "         <div class=\"media\">";
                        echo "            <div class=\"align-self-start mr-2\"><img class=\"media-object rounded\" style=\"width: 48px; height: 48px;\" src=\"$mailername\" alt=\"Image\"></div>";
                        echo "<div class=\"media-body clearfix\"><small class=\"float-right\">".date('j M Y',$date_sent)."<a class=\"ml-3 btn btn-sm btn-danger\" href=\"messages.php?msg=$amid&del=1\">del</a></small>
                                       <p class=\"mb-sm\"><small>$msg_sm</small></p>";
                        echo "            </div>";
                        echo "         </div>";
                        echo "      </div>";
                        echo "   </div>";
                        echo "</div>";
                     }
                     $numrows = $tableset->rowCount();
                     $tableset->closeCursor();
                  }
                  ?>
</div>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
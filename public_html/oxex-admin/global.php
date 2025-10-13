<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Global Data";

setAdminVars(0); // Dashboard section
$subtitle = " ";
$listname = "Global";
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
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0;# only 1 record
  $which = (int)$which;

if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : 0;
    $which = (int)$which;
  $days_fwd = isset($_POST['days_fwd']) ? $_POST['days_fwd'] : 0;
   $days_fwd = (int)$days_fwd;
   $last_date = isset($_POST['last_date']) ? $_POST['last_date'] : '';
    $datefrom = preg_replace("/[^0-9]/", "", $last_date);
    $fromddd = substr($datefrom, 0, 2);
    $frommm = substr($datefrom, 2, 2);
    $fromyyyy = substr($datefrom, 4, 4);
    $date1 = $fromyyyy.$frommm.$fromddd;
  $next_date = isset($_POST['next_date']) ? $_POST['next_date'] : '';
    $dateto = preg_replace("/[^0-9]/", "", $next_date);
    $toddd = substr($dateto, 0, 2);
    $tomm = substr($dateto, 2, 2);
    $toyyyy = substr($dateto, 4, 4);
    $date2 = $toyyyy.$tomm.$toddd;
   if ($date2 > $date1) {
      $datefrom = $date1;
      $dateto = $date2;
      $dispdatefrom = strtotime($datefrom);
      $dispdateto = strtotime($dateto);
    } else {
      $datefrom = $date2;
      $dateto = $date1;
      $dispdatefrom = strtotime($datefrom);
      $dispdateto = strtotime($dateto);
    }
    $recorder_time = isset($_POST['recorder_time']) ? $_POST['recorder_time'] : 9;
    $recorder_email = isset($_POST['recorder_email']) ? $_POST['recorder_email'] : '';
    
  // Update record
  $stmt = $pdo->prepare("UPDATE sys_global SET days_fwd = ?, last_date = ?, next_date = ?, recorder_email = ?, recorder_time = ? WHERE sgid = ?"); 
  $stmt->execute([$days_fwd, $datefrom, $dateto, $recorder_email, $recorder_time, $value1]);
  //printf("[%d] %s\n", $pdo->errorCode(), $pdo->errorInfo()[2]);
  $stmt->closeCursor();
}
?>
<?php
  // find the required record
$stmt = $pdo->prepare("SELECT days_fwd, last_date, next_date, recorder_email, recorder_time FROM sys_global WHERE sgid =  ? ");
$stmt->execute([$value1]); 
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$days_fwd = $row['days_fwd'];
$last_date = $row['last_date'];
$next_date = $row['next_date'];
$recorder_email = $row['recorder_email'];
$recorder_time = $row['recorder_time'];
$stmt->closeCursor();
$dispdatelast = strtotime($last_date);
$dispdatenext = strtotime($next_date);
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
               <div class="content-title"><?php echo $pagetitle ?></div>
            </div>

            <div class="row my-5">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $pagetitle ?></div>
                        </div>
                        <div class="card-body">
                          <div class="form-group">
                              <label class="col-form-label" for="days_fwd">Days look forward</label>
                              <input class="form-control" type="number" id="days_fwd" name="days_fwd" value="<?php echo $days_fwd ?>" required>
                           </div>
                           <div class="form-group">
                            <label for="datepicker">Previous Date of invoice run</label>
                            <input type="text" class="form-control" id="datepicker" name="last_date" placeholder="dd-mm-yyyy" value="<?php echo date('d-m-Y', $dispdatelast) ?>" required>
                          </div>
                          <div class="form-group">
                            <label for="datepicker2">Next Date of invoice run</label>
                            <input type="text" class="form-control" id="datepicker2" name="next_date" placeholder="dd-mm-yyyy" value="<?php echo date('d-m-Y', $dispdatenext) ?>" required>
                          </div>
                          <div class="form-group">
                              <label class="col-form-label" for="recorder_time">Time (hour) of reminder</label>
                              <input class="form-control" type="number" id="recorder_time" name="recorder_time" value="<?php echo $recorder_time ?>" min="1" max="23" required step="1">
                           </div>
                          <div class="form-group">
                              <label class="col-form-label" for="recorder_email">Reminder Email Recipient</label>
                              <input class="form-control" type="text" id="recorder_email" name="recorder_email" value="<?php echo $recorder_email ?>" required>
</div>
                        <div class="card-footer">
                           <input type="hidden" name="done" value="done">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
</div><!-- END card-->
                  </form>
</div>

         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
  <script>
  jQuery(document).ready(function($) {
    $( function() {
      $( "#datepicker" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
    });
    $( function() {
      $( "#datepicker2" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
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
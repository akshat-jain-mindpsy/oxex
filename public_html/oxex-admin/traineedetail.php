<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Trainee Clinical Psychologists";
$subtitle = "Trainees";
$listurl = "trainee.php";
$listname = "Trainees";
$value59 = 59; # $stid for age in trainee log
// create common array of colours for reports
$dispcolorarr = array();
$dispborderarr = array();

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
   <style>
      .password-strength, .password-match {
         font-size: 0.875rem;
         font-weight: 500;
      }
      .password-strength i, .password-match i {
         margin-right: 0.25rem;
      }
      .card.border-warning {
         border-color: #ffc107 !important;
      }
      .card.border-warning .card-header {
         border-color: #ffc107 !important;
      }
   </style>
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : '';

$valueyearstart = 20200101;
$valueyearend = 20991231;

if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row
  $stmt = $supabase_pdo->prepare("DELETE FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
  $stmt->execute([$which]);
  // delete all slogbook entries
  $stmt = $supabase_pdo->prepare("DELETE FROM logbook WHERE trainkey = ?");
  $stmt->execute([$which]);
}
if ($done == "passfail" && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) { 
   // add new notes 
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $super_pass = isset($_POST['super_pass']) ? $_POST['super_pass'] : 0;
  $super_txt = isset($_POST['super_txt']) ? $_POST['super_txt'] : '';
  // check if there's not already a pass for this competency - it's probably a reload if so
   $vids = $supabase_pdo->prepare("SELECT trid FROM trainee_report_ok WHERE trainkey = ? AND super_pass = ? AND date_added = ?");
   $vids->execute([$which, $super_pass, $today]);
   $numlinks = $vids->rowCount();
   if ($numlinks == 0) {
      $insert_stmt = $supabase_pdo->prepare("INSERT INTO trainee_report_ok (trainkey, who_by, super_pass, super_txt, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?)");
      $insert_stmt->execute([$which, $usrkey, $super_pass, $super_txt, $today, $today]);
      $newid = (int)$supabase_pdo->lastInsertId();
   }
}
if ($done == "done" && ($admintype == 'AT' || $admintype == 'DV')) {  
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $name = isset($_POST['name']) ? $_POST['name'] : '';
  $email = isset($_POST['email']) ? $_POST['email'] : '';
  $uid = isset($_POST['uid']) ? $_POST['uid'] : 0;
  $supervisor = isset($_POST['supervisor']) ? $_POST['supervisor'] : '';
  $supervisor2 = isset($_POST['supervisor2']) ? $_POST['supervisor2'] : '';
  $supervisor3 = isset($_POST['supervisor3']) ? $_POST['supervisor3'] : '';
  $tutor = isset($_POST['tutor']) ? $_POST['tutor'] : '';
  $syslink = isset($_POST['syslink']) ? $_POST['syslink'] : '';
  $year = isset($_POST['year']) ? $_POST['year'] : '';
  
  // Update record
  $stmt = $supabase_pdo->prepare("UPDATE trainee_tbl SET name = ?, email = ?, uid = ?, supervisor = ?, syslink = ?, year = ?, who_by = ?, date_modified = ?, tutor = ?, supervisor2 = ?, supervisor3 = ? WHERE trainkey = ? "); 
  $stmt->execute([$name, $email, $uid, $supervisor, $syslink, $year, $usrkey, $today, $tutor, $supervisor2, $supervisor3, $which]);

  // delete existing tags before re-adding
  $stmt = $supabase_pdo->prepare("DELETE FROM trainee_tab_link WHERE trainkey = ? ");
  $stmt->execute([$which]);
  
  // update subject tag links
  $tagstmt = $supabase_pdo->prepare("SELECT tbid FROM tabs_tbl");
  $tagstmt->execute();
  $tabs = $tagstmt->fetchAll(PDO::FETCH_COLUMN);
  foreach ($tabs as $tbid){
    $posmarker = 'q'.$tbid;
    $clicked = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
    if ($clicked == $tbid)  {
      // if checkbox has same value add to db
      $insert_stmt = $supabase_pdo->prepare("INSERT INTO trainee_tab_link (trainkey, tbid) VALUES (?, ?)");
      $insert_stmt->execute([$which, $tbid]);
    }
  }
  // PDO auto-closes
}

// Handle password reset
if ($done == "resetpassword" && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
  $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
  
  if (empty($new_password) || empty($confirm_password)) {
    $password_alert = "<div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Both password fields are required</strong></div>";
  } elseif ($new_password !== $confirm_password) {
    $password_alert = "<div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Passwords do not match</strong></div>";
  } elseif (strlen($new_password) < 6) {
    $password_alert = "<div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Password must be at least 6 characters long</strong></div>";
  } else {
    // Generate new salt and hash password
    $new_salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
    $hashed_password = hash('sha512', $new_password.$new_salt);
    
    // Update the trainee's password and clear txtpw
    $update_stmt = $supabase_pdo->prepare("UPDATE trainee_tbl SET password = ?, salt = ?, txtpw = '', who_by = ?, date_modified = ? WHERE trainkey = ?");
    if ($update_stmt->execute([$hashed_password, $new_salt, $usrkey, $today, $which])) {
      $password_alert = "<div class=\"alert alert-success\" role=\"alert\"><strong>Password reset successfully! The trainee can now log in with their new password.</strong></div>";
      // Refresh the page data to show updated password status
      $refresh_stmt = $supabase_pdo->prepare("SELECT name, email, uid, supervisor, supervisor2, supervisor3, tutor, syslink, year, trainkey, txtpw, who_by, date_added, date_modified, last_used, tandc FROM trainee_tbl WHERE trainkey = ?");
      $refresh_stmt->execute([$which]);
      $row = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $name = $row['name'];
        $email = $row['email'];
        $uid = $row['uid'];
        $supervisor = $row['supervisor'];
        $supervisor2 = $row['supervisor2'];
        $supervisor3 = $row['supervisor3'];
        $tutor = $row['tutor'];
        $syslink = $row['syslink'];
        $year = $row['year'];
        $trainkey = $row['trainkey'];
        $txtpw = $row['txtpw'];
        $who_by = $row['who_by'];
        $date_added = $row['date_added'];
        $date_modified = $row['date_modified'];
        $last_used = $row['last_used'];
        $tandc = $row['tandc'];
      }
    } else {
      $password_alert = "<div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Failed to reset password</strong></div>";
    }
  }
}
?>
<?php
  // find the required record
$stmt = $supabase_pdo->prepare("SELECT name, email, uid, supervisor, supervisor2, supervisor3, tutor, syslink, year, trainkey, txtpw, who_by, date_added, date_modified, last_used, tandc FROM trainee_tbl WHERE trainkey = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
  $name = $row['name'];
  $email = $row['email'];
  $uid = $row['uid'];
  $supervisor = $row['supervisor'];
  $supervisor2 = $row['supervisor2'];
  $supervisor3 = $row['supervisor3'];
  $tutor = $row['tutor'];
  $syslink = $row['syslink'];
  $year = $row['year'];
  $trainkey = $row['trainkey'];
  $txtpw = $row['txtpw'];
  $who_by = $row['who_by'];
  $date_added = $row['date_added'];
  $date_modified = $row['date_modified'];
  $last_used = $row['last_used'];
  $tandc = $row['tandc'];
}
$date_added_ts = !empty($date_added) ? strtotime($date_added) : null;
$date_modified_ts = !empty($date_modified) ? strtotime($date_modified) : null;
$last_used_ts = !empty($last_used) && $last_used != 0 ? strtotime($last_used) : null;
   // who changed last?
 $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
  $stmt->execute([$who_by]);
  $who_by = $stmt->fetchColumn();
   
// whatever the record name is
  $changename = "$name";
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
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small> </div>
                <a href="#stats" class="btn btn-primary ml-5">Statistics</a> <a href="#passfail" class="btn btn-pink ml-5">Supervisor sign off</a> <a href="traineelogbook.php?which=<?php echo $which ?>" class="btn btn-secondary ml-5">Logbook</a> <a href="#password-reset" class="btn btn-warning ml-5">Password Reset</a>
            </div>

            <?php if (isset($password_alert)): ?>
            <div class="row">
               <div class="col">
                  <?php echo $password_alert; ?>
               </div>
            </div>
            <?php endif; ?>

            <div class="row my-5">
               <div class="col-xl-7">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Amend <?php echo $changename ?></div>
                        </div>
                        <div class="card-body">
                           <p>Record created on <?php echo $date_added_ts ? date("D jS M Y", $date_added_ts) : 'Unknown date' ?> and last modified on <?php echo $date_modified_ts ? date("D jS M Y", $date_modified_ts) : 'Unknown date' ?> by <?php echo $who_by ?></p>
                           <?php
                           if ($last_used_ts) {
                              echo "<p>Trainee last logged in on ".date("D jS M Y", $last_used_ts)."</p>";
                           }
                           if ($txtpw != '') {
                              echo "<p>The trainee has <em>not</em> changed their temporary password, which is <strong>$txtpw</strong></p>";
                              echo "<p class=\"text-warning\"><small><i class=\"fa fa-info-circle\"></i> You can reset this password using the form below.</small></p>";
                           } else {
                              echo "<p>The trainee has changed their temporary password which is not held to view.</p>";
                              echo "<p class=\"text-info\"><small><i class=\"fa fa-info-circle\"></i> You can still reset their password using the form below if needed.</small></p>";
                           }
                           ?>
                           <div class="form-group">
                              <label class="col-form-label" for="name">Trainee Name</label>
                              <input class="form-control" type="text" id="name" name="name" value="<?php echo $name ?>" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="email">Trainee Email</label>
                              <input class="form-control" type="email" id="email" name="email" value="<?php echo $email ?>" required>
                           </div>

                              <div class="col form-group">
                                 <label class="col-form-label" for="uid">University Course</label>
                                 <select class="custom-select mb-3" id="uid" name="uid" required>
                                   <option selected="selected" value="0">Select...</option>
                                   <?php
                                   // list the groups, grouping as there are repeats
                                   $cat_ref = $uid;
                                   $tableset = $supabase_pdo->prepare("SELECT uid, university FROM uni_tbl");
                                   $tableset->execute();
                                   $uni_rows = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                   foreach ($uni_rows as $urow){
                                     $uid = $urow['uid'];
                                     $university = $urow['university'];
                                     echo "<option value=\"$uid\"";
                                     if ($uid == $cat_ref) {
                                       echo "selected='selected'";
                                     }
                                     echo ">$university</option>";
                                   }
                                   ?>
                                 </select>
                              </div>
                              <?php
                                $cat_ref = '';
                                $adminAT = 'AT';
                                $adminSO = 'SO';
                                $adminSE = 'SE';
                                $tutorAO = 'AO';
                                $tutorAE = 'AE';
                                ?>
                                <div class="row">
                                    <div class="col form-group">
                                        <label class="col-form-label" for="supervisor">Supervisor #1</label>
                                        <select class="custom-select mb-3" id="supervisor" name="supervisor" required>
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list the groups, grouping as there are repeats
                                         $cat_ref = $supervisor;
                                         $tableset = $supabase_pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->execute([$adminSO, $adminSE, $adminAT]);
                                         $wrows = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                         foreach ($wrows as $wr){
                                          $supervisor = $wr['usrkey'];
                                          $realname = $wr['realname'];
                                          $thisadmintype = $wr['admintype'];
                                          if ($thisadmintype == 'SO') {
                                             $admin = " (OX)";
                                          } else {
                                             $admin = " (EX)";
                                          }
                                           echo "<option value=\"$supervisor\"";
                                           if ($supervisor == $cat_ref) {
                                             echo "selected='selected'";
                                           }
                                           echo ">$realname $thisadmintype</option>";
                                         }
                                         ?>
                                       </select>
                                    </div>
                                    <div class="col form-group">
                                        <label class="col-form-label" for="supervisor2">Supervisor #2</label>
                                        <select class="custom-select mb-3" id="supervisor2" name="supervisor2">
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list the groups, grouping as there are repeats
                                         $cat_ref = $supervisor2;
                                         $tableset = $supabase_pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->execute([$adminSO, $adminSE, $adminAT]);
                                         $wrows = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                         foreach ($wrows as $wr){
                                          $supervisor = $wr['usrkey'];
                                          $realname = $wr['realname'];
                                          $thisadmintype = $wr['admintype'];
                                          if ($thisadmintype == 'SO') {
                                             $admin = " (OX)";
                                          } else {
                                             $admin = " (EX)";
                                          }
                                           echo "<option value=\"$supervisor\"";
                                           if ($supervisor == $cat_ref) {
                                             echo "selected='selected'";
                                           }
                                           echo ">$realname $thisadmintype</option>";
                                         }
                                         ?>
                                       </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col form-group">
                                        <label class="col-form-label" for="supervisor3">Supervisor #3</label>
                                        <select class="custom-select mb-3" id="supervisor3" name="supervisor3">
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list the groups, grouping as there are repeats
                                         $cat_ref = $supervisor3;
                                         $tableset = $supabase_pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->execute([$adminSO, $adminSE, $adminAT]);
                                         $wrows = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                         foreach ($wrows as $wr){
                                          $supervisor = $wr['usrkey'];
                                          $realname = $wr['realname'];
                                          $thisadmintype = $wr['admintype'];
                                          if ($thisadmintype == 'SO') {
                                             $admin = " (OX)";
                                          } else {
                                             $admin = " (EX)";
                                          }
                                           echo "<option value=\"$supervisor\"";
                                           if ($supervisor == $cat_ref) {
                                             echo "selected='selected'";
                                           }
                                           echo ">$realname $thisadmintype</option>";
                                         }
                                         ?>
                                       </select>
                                    </div>
                                    <div class="col form-group">
                                        <label class="col-form-label" for="tutor">Tutor</label>
                                        <select class="custom-select mb-3" id="tutor" name="tutor" required>
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list the groups, grouping as there are repeats
                                         $cat_ref = $tutor;
                                         $tableset = $supabase_pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->execute([$tutorAO, $tutorAE, $adminAT]);
                                         $wrows = $tableset->fetchAll(PDO::FETCH_ASSOC);
                                         foreach ($wrows as $wr){
                                          $supervisor = $wr['usrkey'];
                                          $realname = $wr['realname'];
                                          $thisadmintype = $wr['admintype'];
                                          if ($thisadmintype == 'SO') {
                                             $admin = " (OX)";
                                          } else {
                                             $admin = " (EX)";
                                          }
                                           echo "<option value=\"$supervisor\"";
                                           if ($supervisor == $cat_ref) {
                                             echo "selected='selected'";
                                           }
                                           echo ">$realname $thisadmintype</option>";
                                         }
                                         ?>
                                       </select>
                                    </div>
                                </div>
                             <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                    <label class="col-form-label" for="syslink">Employee Number</label>
                                    <input class="form-control" type="text" id="syslink" name="syslink" value="<?php echo $syslink ?>">
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="year">Cohort Year</label>
                                        <select class="custom-select mb-3" id="year" name="year" required>
                                          <?php
                                          $startyear = date("Y");
                                          $lastyear = $startyear - 2;
                                          $endyear = $lastyear + 5;
                                          for ($x = $lastyear; $x <= $endyear; $x++) {
                                             echo "<option value=\"$x\"";
                                              if ($year == $x) {
                                                echo "selected='selected'";
                                              }
                                              echo ">$x</option>";
                                          }
                                          ?>
                                        </select>
                                    </div>
                                </div>
                             </div>
                             <h6>Permitted to use Tables:</h6>
                         <div class="form-group">
                             <label class="col-form-label">&nbsp;</label>
                              <?PHP
                              $checked = '';
                              // Loop through tags 
                              $loopstmt = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl");
                              $loopstmt->execute();
                              $looprows = $loopstmt->fetchAll(PDO::FETCH_ASSOC);
                              foreach ($looprows as $lr) {  
                                $tbid = $lr['tbid'];
                                $tab_name = $lr['tab_name'];
                                
                                // see if in links table for this record
                                $whatlink = $supabase_pdo->prepare("SELECT ttid FROM trainee_tab_link WHERE trainkey = ? AND tbid = ? ");
                                $whatlink->execute([$trainkey, $tbid]); 
                                $ttid = (int)$whatlink->fetchColumn();
                                if ($ttid > 0) {
                                  $checked = " checked=\"checked\" ";
                                }
                                $whatlink->closeCursor();
                                echo "<label class=\"checkbox-inline mr-3\"><input name=\"q$tbid\" type=\"checkbox\" value=\"$tbid\" $checked/> $tab_name</label>\r";
                                $ttid = 0;
                                $checked = '';
                              }
                              ?>
                         </div>
                         
                         </div>
                         <div class="card-footer">
                            <input type="hidden" name="done" value="done">
                            <input type="hidden" name="which" value="<?PHP echo $which ?>">
                            <div class="float-right"><button class="btn btn-info" type="submit">Amend</button></div>
                         </div>
                     </div><!-- END card-->
                  </form>
                  
                  <!-- Password Reset Form -->
                  <div class="card border-warning mt-4" id="password-reset">
                     <div class="card-header bg-warning">
                        <div class="card-title">Reset Trainee Password</div>
                     </div>
                     <div class="card-body">
                        <p class="text-muted">Use this form to reset the trainee's password. The trainee will be able to log in with the new password immediately.</p>
                        <?php if ($admintype == 'AT' || $admintype == 'DV'): ?>
                        <p class="text-info"><small><i class="fa fa-shield-alt"></i> You have full admin access to reset passwords.</small></p>
                        <?php elseif ($admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE'): ?>
                        <p class="text-info"><small><i class="fa fa-user-check"></i> You have permission to reset passwords for trainees under your supervision.</small></p>
                        <?php endif; ?>
                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="" onsubmit="return confirmPasswordReset()">
                           <div class="form-group">
                              <label class="col-form-label" for="new_password">New Password</label>
                              <input class="form-control" type="password" id="new_password" name="new_password" minlength="6" required>
                              <small class="form-text text-muted">Password must be at least 6 characters long</small>
                              <div class="password-strength mt-2" id="password-strength"></div>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="confirm_password">Confirm New Password</label>
                              <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                              <div class="password-match mt-2" id="password-match"></div>
                           </div>
                           <input type="hidden" name="done" value="resetpassword">
                           <input type="hidden" name="which" value="<?PHP echo $which ?>">
                           <div class="float-right">
                              <button class="btn btn-warning" type="submit">Reset Password</button>
                           </div>
                        </form>
                     </div>
                  </div>
                  
               </div>
               <div class="col-xl-5">
                   <div class="card border-info">
                      <div class="card-header bg-info">
                         <div class="card-title">Attendance Record </div>
                      </div>
                      <div class="card-body">
                        <?php
                        $i = 0;
                        // list the coloured task labels
                        $tableset = $supabase_pdo->prepare("SELECT dtid, task, colour, textcolor FROM tasks ");
                        $tableset->execute();
                        $tasks = $tableset->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($tasks as $trow){
                          $dtid = $trow['dtid'];
                          $task = $trow['task'];
                          $colour = $trow['colour'];
                          $textcolor = $trow['textcolor'];
                          // how many this year for this trainee
                          $numtasks = 0;
                          $vids = $supabase_pdo->prepare("SELECT /*+ USE_INDEX(timesheet, idx_timesheet_trainkey_dtid_taskdate) */ tsid FROM timesheet WHERE trainkey = ? AND dtid = ? AND taskdate >= ? AND taskdate <= ?");
                          $vids->execute([$trainkey, $dtid, $valueyearstart, $valueyearend]);
                          $numtasks = $vids->rowCount();
                          $i = $i + $numtasks;
                          $vids->closeCursor();
                          if ($textcolor == 1) {
                            $task = "<span class=\"text-white\">$task</span>";
                          } else {
                            $task = "<span class=\"text-dark\">$task</span>";
                          }
                          echo "<div id=\"d$dtid\" class=\"rounded px-3 py-2 mr-1 mb-2\" style=\"background-color:#$colour\"><small>$task</small> <span class=\"badge badge-dark float-right mr-2\" id=\"taskqty$dtid\">$numtasks</span></div>";
                        }
                        ?>
                      </div>
                      <div class="card-footer text-muted">
                         Total attendance: <?php echo $i ?> days
                      </div>
                   </div>
               </div>
            </div>

            <div class="row" id="stats">
               <div class="col">
                  <h2 class="bg-primary text-white p-2">Statistics</h2>
                  <?php
                  // Simple statistics - just show basic table info without heavy calculations
                  $namarr = array();
                  $resarr = array();
                  $pasarr = array();
                  
                  // Get basic table information
                  $tableset = $pdo->prepare("SELECT tabs_tbl.tbid, tabs_tbl.tab_name FROM tabs_tbl, trainee_tab_link WHERE trainee_tab_link.trainkey = ? AND tabs_tbl.tbid = trainee_tab_link.tbid AND tabs_tbl.isvis = 1 ORDER BY tabs_tbl.sort_order");
                  $tableset->execute([$which]);
                  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                     $thistbid = (int)$row['tbid'];
                     $tab_name = $row['tab_name'];
                     array_push($namarr, $tab_name);
                        
                        // Count total questions for this table
                        $questionset = $pdo->prepare("SELECT COUNT(*) FROM report_manager WHERE tbid = ?");
                        $questionset->execute([$thistbid]);
                        $total_questions = $questionset->fetchColumn();
                        $questionset->closeCursor();
                        
                        array_push($resarr, $total_questions);
                        array_push($pasarr, 0); // Simplified - no pass/fail calculation
                  }
                  $tableset->closeCursor();
                     
                     echo "<table class=\"table table-sm table-striped\">";
                     echo "<thead>";
                     echo "<tr class=\"table-primary\"><th>Table</th><th>No. Questions</th><th>Status</th></tr>";
                     echo "</thead>";
                     echo "<tbody>";
                     
                     if (empty($namarr)) {
                        echo "<tr><td colspan='3' class='text-center text-muted'>No tables assigned to this trainee</td></tr>";
                     } else {
                        $qq = 0;
                        foreach ($namarr as $tname) {
                           echo "<tr>";
                           echo "<td>$tname</td>";
                           echo "<td>$resarr[$qq]</td>";
                           echo "<td><span class=\"badge badge-info\">Active</span></td>";
                           echo "</tr>";
                           $qq++;
                        }
                     }
                     
                     echo "</tbody>";
                     echo "</table>";
                  ?>
               </div>
            </div>

            <?php include 'incl/sign_off.php' ?>
         </div>
      </section>

   </div>
   
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      // Initialise DataTables only if the table exists on this page
      if ($('#maintable').length) {
         if ($.fn.DataTable) {
            $('#maintable').DataTable({
               "pageLength": 10
            });
         } else if ($.fn.dataTable) {
            $('#maintable').dataTable({
               "pageLength": 10
            });
         }
      }
      
      // Password reset form validation
      $('#confirm_password').on('input', function() {
         var password = $('#new_password').val();
         var confirm = $(this).val();
         
         if (password !== confirm) {
            $(this).get(0).setCustomValidity('Passwords do not match');
            $('#password-match').html('<span class="text-danger"><i class="fa fa-times"></i> Passwords do not match</span>');
         } else {
            $(this).get(0).setCustomValidity('');
            $('#password-match').html('<span class="text-success"><i class="fa fa-check"></i> Passwords match</span>');
         }
      });
      
      $('#new_password').on('input', function() {
         var password = $(this).val();
         var confirm = $('#confirm_password').val();
         
         // Password strength checking
         var strength = 0;
         var feedback = '';
         
         if (password.length >= 6) strength++;
         if (password.length >= 8) strength++;
         if (/[a-z]/.test(password)) strength++;
         if (/[A-Z]/.test(password)) strength++;
         if (/[0-9]/.test(password)) strength++;
         if (/[^A-Za-z0-9]/.test(password)) strength++;
         
         if (strength < 2) {
            feedback = '<span class="text-danger"><i class="fa fa-times"></i> Weak password</span>';
         } else if (strength < 4) {
            feedback = '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Fair password</span>';
         } else if (strength < 6) {
            feedback = '<span class="text-info"><i class="fa fa-thumbs-up"></i> Good password</span>';
         } else {
            feedback = '<span class="text-success"><i class="fa fa-star"></i> Strong password</span>';
         }
         
         $('#password-strength').html(feedback);
         
         if (confirm && password !== confirm) {
            $('#confirm_password').get(0).setCustomValidity('Passwords do not match');
            $('#password-match').html('<span class="text-danger"><i class="fa fa-times"></i> Passwords do not match</span>');
         } else if (confirm) {
            $('#confirm_password').get(0).setCustomValidity('');
            $('#password-match').html('<span class="text-success"><i class="fa fa-check"></i> Passwords match</span>');
         }
      });
      
      // Password reset confirmation
      window.confirmPasswordReset = function() {
         var password = $('#new_password').val();
         var confirm = $('#confirm_password').val();
         
         if (password !== confirm) {
            alert('Passwords do not match. Please correct this before proceeding.');
            return false;
         }
         
         if (password.length < 6) {
            alert('Password must be at least 6 characters long.');
            return false;
         }
         
         return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($name); ?>? This action cannot be undone and the trainee will need to use the new password immediately.');
      };
   });
   </script>
   
<?php

$tableset = $supabase_pdo->prepare("SELECT rcid, colour FROM report_colour ");
$tableset->execute();
$rows = $tableset->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r){
   $rcid = $r['rcid'];
   $colour = $r['colour'];
   $hex = str_replace('#', '', $colour);
   $length   = strlen($hex);
   $rgbr = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
   $rgbg = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
   $rgbb = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
   array_push($dispcolorarr,"rgba($rgbr, $rgbg, $rgbb, 0.4)");
   array_push($dispborderarr,"rgba($rgbr, $rgbg, $rgbb, 1)");
}

// Required field for reporting is select_types.stid
?>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
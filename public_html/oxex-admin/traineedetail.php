<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Trainee Clinical Psychologists";
$subtitle = "Trainees";
$listurl = "trainee.php";
$listname = "Trainees";
$value59 = 59; # $stid for age in trainee log
// create common array of colours for reports
$dispcolorarr = array();
$dispborderarr = array();

if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
$thisyear = date("Y"); // Define this first before using it
$graph = isset($_GET['graph']) ? $_GET['graph'] : 'bar';
$start = isset($_GET['start']) ? $_GET['start'] : $thisyear;
$end = isset($_GET['end']) ? $_GET['end'] : $thisyear;
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delicon = isset($_GET['delicon']) ? $_GET['delicon'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : '';

// set up dates for js array data
$datestart = $start.'0101';
$dateend = $end.'1231';
$datestart = 20200101; #TEMP DATES
$dateend = 20991231; #TEMP DATES
$valueyearstart = 20200101;
$valueyearend = 20991231;

if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // delete row
  $stmt = $mysqli->prepare("DELETE FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
  $stmt->bind_param("s", $which); 
  $stmt->execute();
  $stmt->close();
  // delete all slogbook entries
  $stmt = $mysqli->prepare("DELETE FROM logbook WHERE trainkey = ?");
  $stmt->bind_param("s", $which); 
  $stmt->execute();
  $stmt->close();
}
if ($done == "passfail" && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) { 
   // add new notes 
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $super_pass = isset($_POST['super_pass']) ? $_POST['super_pass'] : 0;
  $super_txt = isset($_POST['super_txt']) ? $_POST['super_txt'] : '';
  // check if there's not already a pass for this competency - it's probably a reload if so
   $vids = $mysqli->prepare("SELECT trid FROM trainee_report_ok WHERE trainkey = ? AND super_pass = ? AND date_added = ?");
   $vids->bind_param("sii", $which, $super_pass, $today);
   $vids->execute();
   $vids->store_result();
   $numlinks = $vids->num_rows;
   $vids->close();
   if ($numlinks == 0) {
      $insert_stmt = $mysqli->prepare("INSERT INTO trainee_report_ok (trainkey, who_by, super_pass, super_txt, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?)");
      $insert_stmt->bind_param("ssisii", $which, $usrkey, $super_pass, $super_txt, $today, $today);
      $insert_stmt->execute();
         //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
      $newid = $insert_stmt->insert_id;
      $insert_stmt->close();
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
  $stmt = $mysqli->prepare("UPDATE trainee_tbl SET name = ?, email = ?, uid = ?, supervisor = ?, syslink = ?, year = ?, who_by = ?, date_modified = ?, tutor = ?, supervisor2 = ?, supervisor3 = ? WHERE trainkey = ? "); 
  $stmt->bind_param("ssissisissss", $name, $email, $uid, $supervisor, $syslink, $year, $usrkey, $today, $tutor, $supervisor2, $supervisor3, $which);
  $stmt->execute();
  $stmt->close();

  // delete existing tags before re-adding
  $stmt = $mysqli->prepare("DELETE FROM trainee_tab_link WHERE trainkey = ? ");
  $stmt->bind_param("s",$which);     
  $stmt->execute();
  $stmt->close();
  
  // update subject tag links
  $tagstmt = $mysqli->prepare("SELECT tbid FROM tabs_tbl");
  $tagstmt->execute();
  $tagstmt->store_result();
  $tagstmt->bind_result($tbid);
  while ($tagstmt->fetch()){
    $posmarker = 'q'.$tbid;
    $clicked = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
    if ($clicked == $tbid)  {
      // if checkbox has same value add to db
      $insert_stmt = $mysqli->prepare("INSERT INTO trainee_tab_link (trainkey, tbid) VALUES (?, ?)");
      $insert_stmt->bind_param("si", $which, $tbid);
      $insert_stmt->execute();
      $insert_stmt->close();
    }
  }
  $tagstmt->close();
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
    $update_stmt = $mysqli->prepare("UPDATE trainee_tbl SET password = ?, salt = ?, txtpw = '', who_by = ?, date_modified = ? WHERE trainkey = ?");
    $update_stmt->bind_param("sssss", $hashed_password, $new_salt, $usrkey, $today, $which);
    
    if ($update_stmt->execute()) {
      $password_alert = "<div class=\"alert alert-success\" role=\"alert\"><strong>Password reset successfully! The trainee can now log in with their new password.</strong></div>";
      // Refresh the page data to show updated password status
      $refresh_stmt = $mysqli->prepare("SELECT name, email, uid, supervisor, supervisor2, supervisor3, tutor, syslink, year, trainkey, txtpw, who_by, date_added, date_modified, last_used, tandc FROM trainee_tbl WHERE trainkey = ?");
      $refresh_stmt->bind_param("s", $which);
      $refresh_stmt->execute();
      $refresh_stmt->store_result();
      $refresh_stmt->bind_result($name, $email, $uid, $supervisor, $supervisor2, $supervisor3, $tutor, $syslink, $year, $trainkey, $txtpw, $who_by, $date_added, $date_modified, $last_used, $tandc);
      $refresh_stmt->fetch();
      $refresh_stmt->close();
      $update_stmt->close();
    } else {
      $password_alert = "<div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Failed to reset password</strong></div>";
      $update_stmt->close();
    }
  }
}
?>
<?php
  // find the required record
$stmt = $mysqli->prepare("SELECT name, email, uid, supervisor, supervisor2, supervisor3, tutor, syslink, year, trainkey, txtpw, who_by, date_added, date_modified, last_used, tandc FROM trainee_tbl WHERE trainkey = ?");
$stmt->bind_param("s", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($name, $email, $uid, $supervisor, $supervisor2, $supervisor3, $tutor, $syslink, $year, $trainkey, $txtpw, $who_by, $date_added, $date_modified, $last_used, $tandc);
$stmt->fetch();
$stmt->close();
   $date_added = strtotime($date_added);
   $date_modified = strtotime($date_modified);
   if ($last_used != 0) {
      $last_used = strtotime($last_used);
   }
   // who changed last?
  $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $who_by);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($who_by);
   $stmt->fetch();
   $stmt->close();
   
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
                <a href="#reports" class="btn btn-purple ml-5">Reports</a> <a href="#stats" class="btn btn-primary ml-5">Statistics</a> <a href="#passfail" class="btn btn-pink ml-5">Supervisor sign off</a> <a href="traineelogbook.php?which=<?php echo $which ?>" class="btn btn-secondary ml-5">Logbook</a> <a href="#password-reset" class="btn btn-warning ml-5">Password Reset</a>
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
                           <p>Record created on <?php echo date("D jS M Y", $date_added) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?> by <?php echo $who_by ?></p>
                           <?php
                           if ($last_used != 0) {
                              echo "<p>Trainee last logged in on ".date("D jS M Y", $last_used)."</p>";
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
                                   $tableset = $mysqli->prepare("SELECT uid, university FROM uni_tbl");
                                   $tableset->execute();
                                   $tableset->store_result();
                                   $tableset->bind_result($uid, $university);
                                   while ($tableset->fetch()){
                                     echo "<option value=\"$uid\"";
                                     if ($uid == $cat_ref) {
                                       echo "selected='selected'";
                                     }
                                     echo ">$university</option>";
                                   }
                                   $tableset->close();
                                   ?>
                                 </select>
                              </div>
                              <?php
                                /*
                                 AT = "Full Admin Control";
                                 AO = "Oxford Course Tutor";
                                 AE = "Exeter Course Tutor";
                                 SO = "Oxford Supervisor";
                                 SE = "Exeter Supervisor";
                                 DV = "Developer";
                                 */
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
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->bind_param("sss", $adminSO, $adminSE, $adminAT);
                                         $tableset->execute();
                                         $tableset->store_result();
                                         $tableset->bind_result($supervisor, $realname, $thisadmintype);
                                         while ($tableset->fetch()){
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
                                         $tableset->close();
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
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->bind_param("sss", $adminSO, $adminSE, $adminAT);
                                         $tableset->execute();
                                         $tableset->store_result();
                                         $tableset->bind_result($supervisor, $realname, $thisadmintype);
                                         while ($tableset->fetch()){
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
                                         $tableset->close();
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
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->bind_param("sss", $adminSO, $adminSE, $adminAT);
                                         $tableset->execute();
                                         $tableset->store_result();
                                         $tableset->bind_result($supervisor, $realname, $thisadmintype);
                                         while ($tableset->fetch()){
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
                                         $tableset->close();
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
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->bind_param("sss", $tutorAO, $tutorAE, $adminAT);
                                         $tableset->execute();
                                         $tableset->store_result();
                                         $tableset->bind_result($supervisor, $realname, $thisadmintype);
                                         while ($tableset->fetch()){
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
                                         $tableset->close();
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
                              $loopstmt = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl");
                              $loopstmt->execute();
                              $loopstmt->store_result();
                              $loopstmt->bind_result($tbid, $tab_name);
                              while ($loopstmt->fetch()) {  
                                
                                // see if in links table for this record
                                $whatlink = $mysqli->prepare("SELECT ttid FROM trainee_tab_link WHERE trainkey = ? AND tbid = ? ");
                                $whatlink->bind_param("si", $trainkey, $tbid);
                                $whatlink->execute(); 
                                $whatlink->bind_result($ttid);
                                $whatlink->fetch();
                                if ($ttid > 0) {
                                  $checked = " checked=\"checked\" ";
                                }
                                $whatlink->close();
                                echo "<label class=\"checkbox-inline mr-3\"><input name=\"q$tbid\" type=\"checkbox\" value=\"$tbid\" $checked/> $tab_name</label>\r";
                                $ttid = 0;
                                $checked = '';
                              }
                              $loopstmt->close();
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
                        <?php if (isset($password_alert)) echo $password_alert; ?>
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
                  
                  <div class="card border-purple">
                     <div class="card-header bg-purple">
                        <div class="card-title">
                           Report Sets (choose style &amp; date)
                        </div>
                        <div class="card-body">
                           <?php
                           // show bar/pie chart buttons for each year of tuition
                           // $thisyear in sess.php. $year is their 'cohort' year
                           /*
                           for ($x = $year; $x <= $thisyear; $x++) {
                              echo "<a class=\"btn btn-sm btn-dark ml-1\" href=\"traineedetail.php?which=$trainkey&graph=bar&start=$x&end=$x\">($x) Bar</a> <a class=\"btn btn-sm btn-dark ml-1\" href=\"traineedetail.php?which=$trainkey&graph=pie&start=$x&end=$x\">($x) Pie</a>";
                           }
                           */
                           //if ($year != $thisyear) {
                              // if not the first year, offer graph of entire stay
                              echo "<br><a class=\"btn btn-sm btn-dark ml-1 mt-2\" href=\"traineedetail.php?which=$trainkey&graph=bar&start=year&end=x\">(Bar</a> <a class=\"btn btn-sm btn-dark ml-1 mt-2\" href=\"traineedetail.php?which=$trainkey&graph=pie&start=year&end=x\">Pie</a>";
                           //}
                           ?>
                           
                        </div>
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
                        //$valueyearstart = date('Y').'0101'; # YYYYMMDD format
                        //$valueyearend = date('Y').'1231';
                        $i = 0;
                        // list the coloured task labels
                        $tableset = $mysqli->prepare("SELECT dtid, task, colour, textcolor FROM tasks ");
                        $tableset->execute();
                        $tableset->store_result();
                        $tableset->bind_result($dtid, $task, $colour, $textcolor);
                        while ($tableset->fetch()){
                          // how many this year for this trainee
                          $numtasks = 0;
                          $vids = $mysqli->prepare("SELECT tsid FROM timesheet WHERE trainkey = ? AND dtid = ? AND taskdate >= ? AND taskdate <= ?");
                          $vids->bind_param("siii", $trainkey, $dtid, $valueyearstart, $valueyearend);
                          $vids->execute();
                          $vids->store_result();
                          $numtasks = $vids->num_rows;
                          $i = $i + $numtasks;
                          $vids->close();
                          if ($textcolor == 1) {
                            $task = "<span class=\"text-white\">$task</span>";
                          } else {
                            $task = "<span class=\"text-dark\">$task</span>";
                          }
                          echo "<div id=\"d$dtid\" class=\"rounded px-3 py-2 mr-1 mb-2\" style=\"background-color:#$colour\"><small>$task</small> <span class=\"badge badge-dark float-right mr-2\" id=\"taskqty$dtid\">$numtasks</span></div>";
                        }
                        $tableset->close();
                        ?>
                     </div>
                     <div class="card-footer text-muted">
                        Total  attendance: <?php echo $i ?> days
                     </div>
                  </div>
               </div>
            </div>

            <?php
            // decide how to key graphs with dates
            // could be a year or a year range
            if ($start == $end) {
               $dispyear = $start;
            } else {
               $dispyear = "$start to $end";
            }
            ?>
<!-- reports -->

            <div class="row" id="reports">

               <?php
               /*
               Each session a trainee logs has a unique $logkey
               Each data is recorded seperately and linked by the $logkey so all answers in a sesion will have the same $logkey
               So we can filter a set of $logkey data against specifics, e.g. only use the data if the same $logkey records a Learning Disability ($stid == 2)

               */
               // these are for stats at end
               $allpass = 0; # flag for how many passed in total
               $allreports = 0; # flag for how many graphs in total
               $namarr = array(); # array of table names
               $pasarr = array(); # array of passes in each table
               $resarr = array(); # array of results in each table
               $prevtabname = '';
               // Create divs for graphs from report manager
               // All have IDs that align with equivalent javascript
               // first loop through Tables (that are agreed for thsi Trainee)
               $tableset = $mysqli->prepare("SELECT tabs_tbl.tbid, tabs_tbl.tab_name FROM tabs_tbl, trainee_tab_link WHERE trainee_tab_link.trainkey = ? AND tabs_tbl.tbid = trainee_tab_link.tbid AND tabs_tbl.isvis = 1 ORDER BY tabs_tbl.sort_order");
               $tableset->bind_param("s", $which);
               $tableset->execute();
               $tableset->store_result();
               $tableset->bind_result($thistbid, $tab_name);
               while ($tableset->fetch()){
                  array_push($namarr, $tab_name);
                  // put table name as heading if changed
                  if ($prevtabname != $tab_name) {
                     echo "\n\n<div class=\"col-xl-12 text-center text-white bg-dark mb-2 pt-3\"><h2>$tab_name <a href=\"#passfail\" class=\"btn btn-sm btn-pink ml-3\">Supervisor sign off</a></h2></div>\n\n";
                  }


                  $reportset = $mysqli->prepare("SELECT rmid, report_title, valtype, situation, stid, elldee, age, agefrom, ageto FROM report_manager WHERE tbid = ? ORDER BY sort_order");
                  $reportset->bind_param("i", $thistbid);
                  $reportset->execute();
                  $reportset->store_result();
                  $reportset->bind_result($rmid, $report_title, $valtype, $situation, $stid, $elldee, $age, $agefrom, $ageto);
                  while ($reportset->fetch()){
                     $allreports++; # count No. of reports
                     $howmanyans = 0; # reset No of passed results
                     $tothrs = 0; # accumulative No hrs
// START data collect

                     $ansarr = array();
                     $valarr = array();
                     $valBarr = array();
                     // look at trainee's data
                     if ($valtype == 0) { # Exact values
                        // loop through this report's data requirements held in valuea
                        // and get the matching field name (valuea = pid)
                        $dataset = $mysqli->prepare("SELECT select_gen.select_val, report_data.valuea FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
                        $dataset->bind_param("i", $rmid); 
                        $dataset->execute();
                        $dataset->store_result();
                        $dataset->bind_result($select_val, $valuea);
                        while ($dataset->fetch()){
                           array_push($valarr,$valuea); # the id's to look for in Trainee's data
                        }
                        $dataset->close();
                        
                        // find how many of each type for this trainee and push value to array
                        foreach ($valarr as $valueA) {
                           $valueA = (int)$valueA;
                           
                           $numages = 0;
                           // 20231115 removed date start & end from query
                           $vids = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ?"); 
                           $vids->bind_param("sii", $trainkey, $stid, $valueA);
                           $vids->execute();
                           $vids->store_result();
                           $vids->bind_result($checklogkey);
                           while ($vids->fetch()){
// 20231116 - removed "//$numages = 0; # clear data if " lines
                              if ($elldee == 1) {
//echo "($stid $valueA $checklogkey) ";
                                 // Any data must have Learning Disability selected, else data not used
                                 // For this $logkey, check the LD 'Clinical Specialism' field data
                                 // this is $stid = 2, $pid = 3
                                 $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                                 $ldstmt->bind_param("iis", $value2, $value3, $checklogkey);
                                 $ldstmt->execute();
                                 $ldstmt->store_result();
                                 $numld = $ldstmt->num_rows;
                                 $ldstmt->fetch();
                                 $ldstmt->close();

                                 if ($numld == 0) { # no LD 'Placement Type'
                                    //$numages = 0; # clear data if 'LD' not selected in Clinical Specialism question for the same data set specified by $checklogkey
                                 } else {
                                    //$numages++; # count the valid data
                                    $numages = $numages + 1;
                                 }
                              }
                              if ($elldee == 2) {
                                 // Any data must have CYP selected, else data not used
                                 // For this $logkey, check the CYP 'Clinical Specialism' field data
                                 // this is $stid = 2, $pid = 4
                                 $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                                 $ldstmt->bind_param("iis", $value2, $value4, $checklogkey);
                                 $ldstmt->execute();
                                 $ldstmt->store_result();
                                 $numld = $ldstmt->num_rows;
                                 $ldstmt->close();

                                 if ($numld == 0) { # no CYP 'Clinical Specialism'
                                    //$numages = 0; # clear data if 'CYP' not selected in Placement Type question for the same data set specified by $checklogkey
                                 } else {
                                    $numages++; # count the valid data
                                 }
                              }
                              if ($elldee == 3) {
                                 // Any data must have OA selected, else data not used
                                 // For this $logkey, check the OA 'Clinical Specialism' field data
                                 // this is $stid = 2, $pid = 2
                                 $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                                 $ldstmt->bind_param("iis", $value2, $value2, $checklogkey);
                                 $ldstmt->execute();
                                 $ldstmt->store_result();
                                 $numld = $ldstmt->num_rows;
                                 $ldstmt->close();

                                 if ($numld == 0) { # no OA 'Clinical Specialism'
                                    //$numages = 0; # clear data if 'OA' not selected in Placement Type question for the same data set specified by $checklogkey
                                 } else {
                                    $numages++; # count the valid data
                                 }
                              }
                              if ($elldee == 4) {
                                 // Any data must have WAA selected, else data not used
                                 // For this $logkey, check the WAA 'Clinical Specialism' field data
                                 // this is $stid = 2, $pid = 1
                                 $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                                 $ldstmt->bind_param("iis", $value2, $value1, $checklogkey);
                                 $ldstmt->execute();
                                 $ldstmt->store_result();
                                 $numld = $ldstmt->num_rows;
                                 $ldstmt->close();

                                 if ($numld == 0) { # no WAA 'Clinical Specialism'
                                    //$numages = 0; # clear data if 'WAA' not selected in Placement Type question for the same data set specified by $checklogkey
                                 } else {
                                    $numages++; # count the valid data
                                 }
                              }
                              if ($age == 1 && $elldee == 0) {
                                 // patient's age is in $stid = 59
                                 if ($agefrom == 0) {
                                    $agefrom = 0.1;# avoids patients where age isn't recorded
                                 }
                                 // need to check age is within range for this logkey
                                 $agestmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE stid = ? AND logkey = ?");
                                 $agestmt->bind_param("is", $value59, $checklogkey);
                                 $agestmt->execute();
                                 $agestmt->store_result();
                                 $agestmt->bind_result($patientage);
                                 $agestmt->fetch();
                                 $agestmt->close();
                                 if ($patientage >= $agefrom && $patientage <= $ageto) {
                                    $numages++;
                                 }
                              }
                              if ($elldee == 0 && $age == 0) {
                                 $numages++; # count all the data as LD/age N/A
                              }
//echo " *$numld $numages\n";
                           }
                           $vids->close();
                           $numld = 0; # reset
                           
                           array_push($ansarr, $numages);
 
                        }
                        
                     }
                     if ($valtype == 1) { #range of values
                        $dataset = $mysqli->prepare("SELECT valuea, valueb FROM report_data WHERE rmid = ? ORDER BY rdid");
                        $dataset->bind_param("i", $rmid); 
                        $dataset->execute();
                        $dataset->store_result();
                        $dataset->bind_result($valuea, $valueb);
                        while ($dataset->fetch()){
                           $select_val = "$valuea - $valueb";
                           array_push($valarr,$valuea); # the 'from' value to search data
                           array_push($valBarr,$valueb); # the 'to' value to search data
                        }
                        $dataset->close();
                        // find how many of each type for this trainee and push value to array
                        $x = 0;
                        foreach ($valarr as $valueA) {
                           // find same key for valueB
                           $valueB = $valBarr[$x];
                           // removed dates
                           // AND date_added >= ? AND date_added <= ?
                           // , $datestart, $dateend
                           $vids = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND stid = ? AND select_val >= ? AND select_val <= ?"); 
                           $vids->bind_param("sisi", $trainkey, $stid, $valueA, $valueB);
                           $vids->execute();
                           $vids->store_result();
                           $vids->bind_result($checklogkey);
                           $numages = $vids->num_rows;
                           $vids->close();
                           array_push($ansarr, $numages);

                           $x++;
                        }
                     }
                     if ($valtype == 2) { # count of hours
                        // find select_gen.pid from report_data.valuea
                        // this gives select_types.stid (and select_types.select_val is the x-axis label)
                        // 
                        // loop through this report's data requirements held in valuea
                        // and get the matching field name (valuea = pid)
                        $value60 = 60;
                        $dataset = $mysqli->prepare("SELECT select_gen.select_val, report_data.valuea, report_data.valueb FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
                        $dataset->bind_param("i", $rmid); 
                        $dataset->execute();
                        $dataset->store_result();
                        $dataset->bind_result($select_val, $valuea, $valueb);
                        while ($dataset->fetch()){
                           $valuea = intval($valuea);
                           array_push($valarr,$valuea); # the stid's to look for in Trainee's data
                           array_push($valBarr,$valueb); # the No hrs required in search data
                        }
                        $dataset->close();


                        // loop through each stid in trainee's data matching array pid
                        $tothrs = 0;
                        // removed dates
                           // AND date_added >= ? AND date_added <= ?
                           // , $datestart, $dateend
                        foreach ($valarr as $valueA) {
                           $hrsset = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ?");
                           $hrsset->bind_param("sii", $trainkey, $stid, $valueA);
                           $hrsset->execute();
                           $hrsset->store_result();
                           $hrsset->bind_result($logkey);
                           while ($hrsset->fetch()){
                              $hours = '';
                              // we'll use the logkey to find the hours for the same session
                              //echo "logkey $logkey ($trainkey, $stid, $valueA, $datestart, $dateend)<br>";
                              // for each, find number of hours for the same logkey
                              // // looking for stid = 60
                              $stmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
                              $stmt->bind_param("si", $logkey, $value60);
                              $stmt->execute();
                              $stmt->store_result();
                              $stmt->bind_result($hours);
                              $stmt->fetch();
                              $stmt->close();
                              //echo "| $hours | ";
                              // data is in HH:mm format
                              if ($hours > 0) {
                                 $time = explode(':', $hours);
                                 if (isset($time[0]) && isset($time[1])) {
                                    $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
                                    $hours = $minutes / 60;
                                    $tothrs = $tothrs + $hours;
                                 }
                              }
                              
                           }
                           $numrows = $hrsset->num_rows;
                           $hrsset->close();
                           //echo "valueA $valueA - numrows $numrows";
/*
                           $vids = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ? AND date_added >= ? AND date_added <= ?"); 
                           $vids->bind_param("siiii", $trainkey, $stid, $valueA, $datestart, $dateend);
                           $vids->execute();
                           $vids->store_result();
                           $vids->bind_result($logkey);
                           $vids->fetch();
                           $vids->close();
                           */

                           array_push($ansarr, $tothrs);
                        }
                     }

                     $howmanyvals = count($valarr); # how many values expected
                     
// END data collect



// START pass/fail
                  // $passtext shows pass/fail/no pass requirement
                     
                     // $situation shows requirements for 'pass'
                     include 'incl/situations.php';
// END pass/fail

                     //if ($valtype == 0) { # Exact values
                        echo "<div class=\"col-xl-4 mb-2\">";
                        echo "<div class=\"card border-purple\">";
                        echo "<div class=\"card-header bg-purple\">";
                        $elldeebadge = '';
                        if ($elldee == 1) {
                           // show badge meaning this data must have Leadning Disability
                           $elldeebadge = " <span class=\"badge badge-info\"> LD </span>";
                        }
                        if ($elldee == 2) {
                           // show badge meaning this data must be CYP (young person)
                           $elldeebadge = " <span class=\"badge badge-info\"> CYP </span>";
                        }
                        if ($age == 1) {
                           // show badge meaning this data is age-limited
                           $agebadge = " <span class=\"badge badge-info\"> Age $agefrom - $ageto </span>";
                        } else {
                           $agebadge = '';
                        }
                        echo "<div class=\"card-title\">".htmlentities($report_title)."  $elldeebadge $agebadge ($rmid)</div>";
                        echo "</div>";
                        echo "<div class=\"card-body\">";
                        echo "<canvas id=\"Chart_$rmid\" width=\"400\" height=\"400\"></canvas>";
                        echo "</div>";
                        echo "<div class=\"card-footer\">";
                        echo "$situationtxt $passtext";
                        if ($valtype == 2) {
                           
                           //print_r($valarr);
                           //print_r($ansarr);
                        }
 //print_r($ansarr);
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                     //}
                     // we'll  use these arrays later in the js so empty
                     unset($ansarr);
                     unset($valarr);
                     unset($valBarr);
                     
                  }
                  $reportset->close();
                  $prevtabname = $tab_name;
                  
                  array_push($resarr, $allreports); # how many for this table
                  array_push($pasarr, $allpass); # how many for this table
                  $allpass = 0;
                  $allreports = 0;
               }
               $tableset->close();
               ?>
               
            </div>
            <div class="row" id="stats">
               <div class="col">
                  <h2 class="bg-primary text-white p-2">Statistics </h2>
                  <?php
                  //$pcp = ($allpass/$allreports)*100;
                  //echo "<p>Total of reports: $allreports. Total passed $allpass. Percentage passed ".ceil($pcp)."%</p>";
                  echo "<table class=\"table table-sm table-striped\">";
                  echo "<thead>";
                  echo "<tr class=\"table-primary\"><th>Table</th><th>No. Questions</th><th>No. Passed</th><th>%age Pass</th></tr>";
                  echo "</thead>";
                  echo "<tbody>";
                  $qq = 0;
                  foreach ($namarr as $tname) {
                     echo "<tr>";
                     echo "<td>$tname</td>";
                     echo "<td>$resarr[$qq]</td>";
                     echo "<td>$pasarr[$qq]</td>";
                     if ($resarr[$qq] != 0) {
                        $pcp = ($pasarr[$qq]/$resarr[$qq])*100;
                     } else {
                        $pcp = 0;
                     }
                     $celltxt = "<span class=\"badge badge-danger\">".ceil($pcp)."</span>";
                     if ($pcp > 50) {
                        $celltxt = "<span class=\"badge badge-warning\">".ceil($pcp)."</span>";
                     }
                     if ($pcp > 75) {
                        $celltxt = "<span class=\"badge badge-info\">".ceil($pcp)."</span>";
                     }
                     if ($pcp > 99.9) {
                        $celltxt = "<span class=\"badge badge-success\">".ceil($pcp)."</span>";
                     }
                     echo "<td>$celltxt</td>";
                     echo "</tr>";
                     $qq++;
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
   <?php include 'incl/adminjslite.php' ?>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js" integrity="sha512-s+xg36jbIujB2S2VKfpGmlC3T5V2TF3lY48DX7u2r9XzGzgPsa6wTpOQA7J9iffvdeBN0q9tKzRxVxw1JviZPg==" crossorigin="anonymous"></script>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
        "pageLength": 10
      });
      
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

$tableset = $mysqli->prepare("SELECT rcid, colour FROM report_colour ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($rcid, $colour);
while ($tableset->fetch()){
$hex = str_replace('#', '', $colour);
   $length   = strlen($hex);
   $rgbr = hexdec($length == 6 ? substr($hex, 0, 2) : ($length == 3 ? str_repeat(substr($hex, 0, 1), 2) : 0));
   $rgbg = hexdec($length == 6 ? substr($hex, 2, 2) : ($length == 3 ? str_repeat(substr($hex, 1, 1), 2) : 0));
   $rgbb = hexdec($length == 6 ? substr($hex, 4, 2) : ($length == 3 ? str_repeat(substr($hex, 2, 1), 2) : 0));
   array_push($dispcolorarr,"rgba($rgbr, $rgbg, $rgbb, 0.4)");
   array_push($dispborderarr,"rgba($rgbr, $rgbg, $rgbb, 1)");
 }
$tableset->close();

// Required field for reporting is select_types.stid
?>

<?php
// Create js for graphs from report manager, all use the common graph colours
// All have IDs that align with equivalent div
// first loop through Tables (that are agreed for this Trainee)
// Two types of js, one for ranges and one for exact value
// Exact value use $stid
$labeltxt = '# of Settings';
$tableset = $mysqli->prepare("SELECT tabs_tbl.tbid FROM tabs_tbl, trainee_tab_link WHERE trainee_tab_link.trainkey = ? AND tabs_tbl.tbid = trainee_tab_link.tbid AND tabs_tbl.isvis = 1 ORDER BY tabs_tbl.sort_order");
$tableset->bind_param("s", $which);
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($thistbid);
while ($tableset->fetch()){
   $reportset = $mysqli->prepare("SELECT rmid, valtype, stid, elldee, age, agefrom, ageto FROM report_manager WHERE tbid = ? ORDER BY sort_order");
   $reportset->bind_param("i", $thistbid);
   $reportset->execute();
   $reportset->store_result();
   $reportset->bind_result($rmid, $valtype, $stid, $elldee, $age, $agefrom, $ageto);
   while ($reportset->fetch()){
      
      // Chart_$rmid
      $labelarr = array(); # will fill with x-axis labels
      $valarr = array(); # will fill with the valuea (pid or valueA) data
      $valBarr = array(); # will fill with the valueB data
      $ansarr = array(); # will fill with trainee's data

      echo "<script>\n";
      if ($valtype == 0) { # Exact values
         // loop through this report's data requirements held in valuea
         // and get the matching field name (valuea = pid)
         $dataset = $mysqli->prepare("SELECT select_gen.select_val, report_data.valuea FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
         $dataset->bind_param("i", $rmid); 
         $dataset->execute();
         $dataset->store_result();
         $dataset->bind_result($select_val, $valuea);
         while ($dataset->fetch()){
            $select_val = (strlen($select_val) > 25) ? substr($select_val,0,22).'...' : $select_val; # truncate label if required
            array_push($labelarr,$select_val); #this creates the x-axis labels
            array_push($valarr,$valuea); # the id's to look for in Trainee's data
         }
         $dataset->close();
         //$labelarr = rtrim($labelarr,","); #remove trailing comma
         //$valarr = rtrim($valarr,","); #remove trailing comma

         // find how many of each type for this trainee and push value to array
         foreach ($valarr as $valueA) {
            $numages = 0;
            // removed dates
            // AND date_added >= ? AND date_added <= ?
            // , $datestart, $dateend
            $vids = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ?"); 
            $vids->bind_param("sii", $trainkey, $stid, $valueA);
            $vids->execute();
            $vids->store_result();
            $vids->bind_result($checklogkey);
            while ($vids->fetch()){
               if ($elldee == 1) {
                  // Any data must have Learning Disability selected, else data not used
                  // For this $logkey, check the LD 'Clinical Specialism' field data - this is $stid = 2, $pid = 3
                  $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                  $ldstmt->bind_param("iis", $value2, $value3, $checklogkey);
                  $ldstmt->execute();
                  $ldstmt->store_result();
                  $numld = $ldstmt->num_rows;
                  $ldstmt->close();

                  if ($numld == 0) { # no LD 'Clinical Specialism'
                     //$numages = 0; # clear data if 'LD' not selected in Clinical Specialism question for the same data set specified by $checklogkey
                  } else {
                     $numages++; # count the valid data
                  }
               }
               if ($elldee == 2) {
                  // Any data must have CYP selected, else data not used
                  // For this $logkey, check the CYP 'Clinical Specialism' field data
                  // this is $stid = 2, $pid = 4
                  $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                  $ldstmt->bind_param("iis", $value2, $value4, $checklogkey);
                  $ldstmt->execute();
                  $ldstmt->store_result();
                  $numld = $ldstmt->num_rows;
                  $ldstmt->close();

                  if ($numld == 0) { # no CYP 'Clinical Specialism'
                     //$numages = 0; # clear data if 'CYP' not selected in Placement Type question for the same data set specified by $checklogkey
                  } else {
                     $numages++; # count the valid data
                  }
               }
               if ($elldee == 3) {
                  // Any data must have OA selected, else data not used
                  // For this $logkey, check the OA 'Clinical Specialism' field data
                  // this is $stid = 2, $pid = 2
                  $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                  $ldstmt->bind_param("iis", $value2, $value2, $checklogkey);
                  $ldstmt->execute();
                  $ldstmt->store_result();
                  $numld = $ldstmt->num_rows;
                  $ldstmt->close();

                  if ($numld == 0) { # no OA 'Clinical Specialism'
                     //$numages = 0; # clear data if 'OA' not selected in Placement Type question for the same data set specified by $checklogkey
                  } else {
                     $numages++; # count the valid data
                  }
               }
               if ($elldee == 4) {
                  // Any data must have WAA selected, else data not used
                  // For this $logkey, check the WAA 'Clinical Specialism' field data
                  // this is $stid = 2, $pid = 1
                  $ldstmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE stid = ? AND pid = ? AND logkey = ?");
                  $ldstmt->bind_param("iis", $value2, $value1, $checklogkey);
                  $ldstmt->execute();
                  $ldstmt->store_result();
                  $numld = $ldstmt->num_rows;
                  $ldstmt->close();

                  if ($numld == 0) { # no WAA 'Clinical Specialism'
                     //$numages = 0; # clear data if 'WAA' not selected in Placement Type question for the same data set specified by $checklogkey
                  } else {
                     $numages++; # count the valid data
                  }
               }
               if ($age == 1) {
                  // patient's age is in $stid = 59
                  if ($agefrom == 0) {
                     $agefrom = 0.1;# avoids patients where age isn't recorded
                  }
                  // need to check age is within range for this logkey
                  $agestmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE stid = ? AND logkey = ?");
                  $agestmt->bind_param("is", $value59, $checklogkey);
                  $agestmt->execute();
                  $agestmt->store_result();
                  $agestmt->bind_result($patientage);
                  $agestmt->fetch();
                  $agestmt->close();
                  if ($patientage >= $agefrom && $patientage <= $ageto) {
                     $numages++;
                  }
               }
               if ($elldee == 0 && $age == 0) {
                  $numages++; # count all the data as LD/age N/A
               }
            }
            $vids->close();
            $numld = 0; # reset
            
            array_push($ansarr, $numages);

            
         }
         //$ansarr = rtrim($ansarr,","); #remove trailing comma
      }
      if ($valtype == 1) { #range of values 
         $dataset = $mysqli->prepare("SELECT valuea, valueb FROM report_data WHERE rmid = ? ORDER BY rdid");
         $dataset->bind_param("i", $rmid); 
         $dataset->execute();
         $dataset->store_result();
         $dataset->bind_result($valuea, $valueb);
         while ($dataset->fetch()){
            $select_val = "$valuea - $valueb";
            array_push($labelarr,$select_val); #this creates the x-axis labels
            array_push($valarr,$valuea); # the 'from' value to search data
            array_push($valBarr,$valueb); # the 'to' value to search data
         }
         $dataset->close();
         // find how many of each type for this trainee and push value to array
         $x = 0;
         foreach ($valarr as $valueA) {
            // find same key for vlueB
            $valueB = $valBarr[$x];
            // removed dates
            // AND date_added >= ? AND date_added <= ?
            // , $datestart, $dateend
            $vids = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND stid = ? AND select_val >= ? AND select_val <= ? "); 
            $vids->bind_param("sisi", $trainkey, $stid, $valueA, $valueB);
            $vids->execute();
            $vids->store_result();
            $numages = $vids->num_rows;
            $vids->close();
            array_push($ansarr, $numages);
            $x++;
         }
      }
      if ($valtype == 2) { # count of hours
         $value60 = 60;
         // loop through this report's data requirements held in valuea
         // and get the matching field name (valuea = pid)
         $dataset = $mysqli->prepare("SELECT select_gen.select_val, report_data.valuea FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
         $dataset->bind_param("i", $rmid); 
         $dataset->execute();
         $dataset->store_result();
         $dataset->bind_result($select_val, $valuea);
         while ($dataset->fetch()){
            $valuea = intval($valuea);
            array_push($labelarr,$select_val); #this creates the x-axis labels
            array_push($valarr,$valuea); # the id's to look for in Trainee's data
         }
         $dataset->close();
         //$labelarr = rtrim($labelarr,","); #remove trailing comma
         //$valarr = rtrim($valarr,","); #remove trailing comma

         foreach ($valarr as $valueA) {
            $hours = '';
            $tothours = 0;
            // removed dates
            // AND date_added >= ? AND date_added <= ?
            // , $datestart, $dateend
            $hrsset = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ?");
            $hrsset->bind_param("sii", $trainkey, $stid, $valueA);
            $hrsset->execute();
            $hrsset->store_result();
            $hrsset->bind_result($logkey);
            while ($hrsset->fetch()){
               $hours = 0;
               // we'll use the logkey to find the hours for the same session
               //echo "logkey $logkey ($trainkey, $stid, $valueA, $datestart, $dateend)<br>";
               // for each, find number of hours for the same logkey
               // // looking for stid = 60
               $stmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
               $stmt->bind_param("si", $logkey, $value60);
               $stmt->execute();
               $stmt->store_result();
               $stmt->bind_result($hours);
               $stmt->fetch();
               $stmt->close();

               // data is in HH:mm format
               if ($hours != 0) {
                  $time = explode(':', $hours);
                  if (isset($time[0]) && isset($time[1])) {
                     $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
                     $hours = $minutes / 60;
                     $tothours = $tothours + $hours;
                  }
               }
               
               
               
            }
            array_push($ansarr, $tothours);
            $numrows = $hrsset->num_rows;
            $hrsset->close();
            
         }
      }


         // That concludes setting up the data, now create the chart:
         echo "const ctxx$rmid = document.getElementById('Chart_$rmid').getContext('2d');\n";
         echo "const Chart_$rmid = new Chart(ctxx$rmid, {";#1
            echo "type: '$graph',\n"; # bar or pie
            echo "data: {\n";#2
               echo "labels: [\n"; # print the x-axis labels
                  foreach ($labelarr as $labelval) {
                     echo "'$labelval',";
                  }
               echo "],\n";
               echo "datasets: [{\n
                  label: '$labeltxt',\n
                  data: [\n";
                  foreach ($ansarr as $ans) {
                     echo "'$ans',";
                  }
                  echo "]\n,
                  backgroundColor: [\n
                  ";
                  foreach ($dispcolorarr as $bgcol) {
                     echo "'$bgcol',";
                  }
                  echo "\n],
                  borderColor: [\n";
                  foreach ($dispborderarr as $bdrcol) {
                     echo "'$bdrcol',";
                  }
                  echo "\n],
                  borderWidth: 1
               }]";

            echo "},\n";#2

            echo "options: {
                 scales: {
                     yAxes: [{
                           ticks: {
                              stepSize: 1,
                              beginAtZero: true
                           }
                     }]
                 }
             }";

         echo "});\n";#1
         
      
      echo "</script>\n";
   }
   $reportset->close();
}
$tableset->close();

?>

</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>
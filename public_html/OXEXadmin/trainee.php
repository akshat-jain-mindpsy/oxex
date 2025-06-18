<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
$pagetitle = "Trainee Clinical Psychologists";
$subtitle = "Trainees";
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
</head>
<?php 
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0; # trainkey 32 char
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // wipe data and delete trainee

   // which tables they see
   $stmt = $mysqli->prepare("DELETE FROM trainee_tab_link WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();

   // their recorded data
   $stmt = $mysqli->prepare("DELETE FROM trainee_log WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();

   // their attendance log
   $stmt = $mysqli->prepare("DELETE FROM timesheet WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();

   // any subset
   $stmt = $mysqli->prepare("DELETE FROM subset_link_tbl WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();

   // any report certificate
   $stmt = $mysqli->prepare("DELETE FROM trainee_report_ok WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();

   // delete the trainee
   $stmt = $mysqli->prepare("DELETE FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Trainee's Records Deleted</strong></div></div></div>";
   }
   $stmt->close();

}
if ($del == "wipe" && ($admintype == 'AT' || $admintype == 'DV')) {
   // we're just wiping their data

   // their recorded data
   $stmt = $mysqli->prepare("DELETE FROM trainee_log WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   if ($mysqli->affected_rows > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Trainee Data Wiped</strong></div></div></div>";
   }
   $stmt->close();

   // their attendance log
   $stmt = $mysqli->prepare("DELETE FROM timesheet WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();

   // any report certificate
   $stmt = $mysqli->prepare("DELETE FROM trainee_report_ok WHERE trainkey = ?");
   $stmt->bind_param("s", $which); 
   $stmt->execute();
   $stmt->close();
}

if ($newadmin == 'newadmin') {
  $name = isset($_POST['name']) ? $_POST['name'] : '';
  $email = isset($_POST['email']) ? $_POST['email'] : '';
  $uid = isset($_POST['uid']) ? $_POST['uid'] : 0;
  $supervisor = isset($_POST['supervisor']) ? $_POST['supervisor'] : '';
  $supervisor2 = isset($_POST['supervisor2']) ? $_POST['supervisor2'] : '';
  $supervisor3 = isset($_POST['supervisor3']) ? $_POST['supervisor3'] : '';
  $tutor = isset($_POST['tutor']) ? $_POST['tutor'] : '';
  $syslink = isset($_POST['syslink']) ? $_POST['syslink'] : '';
  $year = isset($_POST['year']) ? $_POST['year'] : '';
  // create a user key and password
    $txtpw = substr(md5(rand()), 0, 8); # a temp password
    // Create a random salt
    $salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
    $password = hash('sha512', $txtpw.$salt);
    
    $numids = 1;
    while (!$numids == 0) {
      // make 32 digit hex string
      $trainkey = substr(md5(rand()), 0, 32);   
      $stmt = $mysqli->prepare("SELECT tid FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
      $stmt->bind_param('s', $trainkey);
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($tid);
      $stmt->fetch();
      $numids = $stmt->num_rows;
      $stmt->close();
    }

  // write new record
  $insert_stmt = $mysqli->prepare("INSERT INTO trainee_tbl (name, email, uid, supervisor, supervisor2, supervisor3, syslink, year, trainkey, txtpw, password, salt, who_by, date_added, date_modified, last_used, tandc, tutor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $insert_stmt->bind_param("ssissssisssssiiiis", $name, $email, $uid, $supervisor, $supervisor2, $supervisor3, $syslink, $year, $trainkey, $txtpw, $password, $salt, $usrkey, $today, $today, $value0, $value1, $tutor);
  $insert_stmt->execute();
  //printf("[%d] %s\n", $mysqli->errno, $mysqli->error);
  $newid = $insert_stmt->insert_id;
  $insert_stmt->close();

  // create table links
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
      $insert_stmt->bind_param("si", $trainkey, $tbid);
      $insert_stmt->execute();
      $insert_stmt->close();
    }
  }
  $tagstmt->close();
  
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
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row">
               <div class="col-xl-12">
                  <div class="table-responsive">
                        <table class="table table-striped my-4 w-100" id="maintable">
                           <thead>
                              <tr>
                                 <th class="sort-alpha" data-priority="1">Trainee</th>
                                 <th>Course</th>
                                 <th>Cohort</th>
                                 <th>Supervisor</th>
                                 <th>Tutor</th>
                                 <th>Welcome email</th>
                                 <th>Attendance <?php echo date('Y') ?></th>
                                 <th>Delete / Wipe !</th>
                              </tr>
                           </thead>
                           <tbody>
<?php
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$tableset = $mysqli->prepare("SELECT trainkey, name, uid, year, supervisor, supervisor2, supervisor3, tutor, txtpw, email FROM trainee_tbl");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($trainkey, $name, $uid, $year, $supervisor, $supervisor2, $supervisor3, $tutor, $txtpw, $email);
while ($tableset->fetch()){
   // get course

   $stmt = $mysqli->prepare("SELECT university FROM uni_tbl WHERE uid = ?");
   $stmt->bind_param("i", $uid);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($university);
   $stmt->fetch();
   $stmt->close();
   // get supervisors
   $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $supervisor);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($supername);
   $stmt->fetch();
   $stmt->close();
   $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $supervisor2);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($supername2);
   $stmt->fetch();
   $stmt->close();
   $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $supervisor3);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($supername3);
   $stmt->fetch();
   $stmt->close();
   // get tutor
   $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $tutor);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($tutorname);
   $stmt->fetch();
   $stmt->close();
   // how many attendance days this year
   $vids = $mysqli->prepare("SELECT tsid FROM timesheet WHERE trainkey = ? AND taskdate >= ? AND taskdate <= ?");
   $vids->bind_param("sii", $trainkey, $valueyearstart, $valueyearend);
   $vids->execute();
   $vids->store_result();
   $numtasks = $vids->num_rows;
   $vids->close();

   // is this admin allowed to see it
   $canIview = 0;
   if ($admintype == 'AT' || $admintype == 'DV') {
      // top admins see all
      $canIview = 1;
   }
   // next, can see if a tutor or supervisor
   if ($supervisor == $usrkey || $supervisor2 == $usrkey || $supervisor3 == $usrkey || $tutor == $usrkey) {
      $canIview = 1;
   }

   if ($canIview == 1) {
?>
<tr>
   <td><a href="traineedetail.php?which=<?php echo $trainkey ?>"><?php echo $name?></a></td>
   <td><?php echo $university ?></td>
   <td><?php echo $year ?></td>
   <td><?php echo "$supername $supername2 $supername3" ?></td>
   <td><?php echo $tutorname ?></td>
   <td><?php echo "<a class=\"btn btn-success\" href=\"mailto:$email?Subject=Your e-log access&body=Hello $name%0D%0A%0D%0AYour e-log access point is https://www.oxex.co.uk/%0D%0A%0D%0AYour username is your Oxford Health email address and your temporary password is $txtpw\">Send</a>" ?></td>
   <td><?php echo $numtasks ?></td>
   <td>
      <?php
      if ($admintype == 'AT' || $admintype == 'DV') {
      ?>
      <div class="btn-group" role="group">
         <button type="button" class="btn btn-danger dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
         Delete/Wipe
         </button>
         <div class="dropdown-menu bg-secondary">
            <a class="btn btn-warning m-2" href="trainee.php?del=wipe&amp;which=<?php echo $trainkey ?>" onclick="return confirm('Are you sure you want to wipe this trainee\'s data?')">Wipe Trainee's data only</a>
            <div class="dropdown-divider"></div>
            <a class="btn btn-danger m-2" href="trainee.php?del=del&amp;which=<?php echo $trainkey ?>" onclick="return confirm('Are you sure you want to delete this trainee\'s entire data?')">Delete Trainee &amp; all data</a>
         </div>
      </div>
      <?php
      } else {
         echo "N/A";
      }
      ?>
   </td>
</tr>
 <?php
} # canIview
 $supername = '';
 $supername2 = '';
 $supername3 = '';
 $tutorname = '';
 }
$numrows = $tableset->num_rows;
$tableset->close();
?>
                           </tbody>
                        </table>
                     </div>
               </div>
            </div><!-- end table row -->
            <?php
            if ($admintype == 'AT' || $admintype == 'DV') {
            ?>
            <div class="row my-5" id="newform">
               <div class="col-xl-8">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                     <!-- START card-->
                     <div class="card border-info">
                        <div class="card-header bg-info">
                           <div class="card-title">Add new Trainee</div>
                        </div>

                        <div class="card-body">
                           <div class="form-group">
                              <label class="col-form-label" for="name">Trainee Name</label>
                              <input class="form-control" type="text" id="name" name="name" required>
                           </div>
                           <div class="form-group">
                              <label class="col-form-label" for="email">Trainee Email</label>
                              <input class="form-control" type="email" id="email" name="email" required>
                           </div>
                           
                                    <div class="form-group">
                                       <label class="col-form-label" for="uid">University Course</label>
                                       <select class="custom-select custom-select-lg mb-3" id="uid" name="uid" required>
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list the groups, grouping as there are repeats
                                         $cat_ref = '';
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
                                        <label class="col-form-label" for="supervisor">Supervisor#1</label>
                                        <select class="custom-select custom-select mb-3" id="supervisor" name="supervisor" required>
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list Supervisors Only (& full admin)
                                         $cat_ref = '';
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE (admintype = ? OR admintype = ? OR admintype = ?)");
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
                                           if ($usrkey == $cat_ref) {
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
                                        <select class="custom-select custom-select mb-3" id="supervisor2" name="supervisor2">
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list Supervisors Only (& full admin)
                                         $cat_ref = '';
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE (admintype = ? OR admintype = ? OR admintype = ?)");
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
                                           if ($usrkey == $cat_ref) {
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
                                        <select class="custom-select custom-select mb-3" id="supervisor" name="supervisor3">
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list Supervisors Only (& full admin)
                                         $cat_ref = '';
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE (admintype = ? OR admintype = ? OR admintype = ?)");
                                         $tableset->bind_param("sss", $adminAO, $adminAE, $adminAT);
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
                                           if ($usrkey == $cat_ref) {
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
                                        <select class="custom-select custom-select mb-3" id="tutor" name="tutor" required>
                                         <option selected="selected" value="0">Select...</option>
                                         <?php
                                         // list Tutors Only (& full admin)
                                         $cat_ref = '';
                                         $tableset = $mysqli->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ?");
                                         $tableset->bind_param("sss", $tutorAO, $tutorAE, $adminAT);
                                         $tableset->execute();
                                         $tableset->store_result();
                                         $tableset->bind_result($tutor, $tutorname, $thisadmintype);
                                         while ($tableset->fetch()){
                                          if ($thisadmintype == 'AO') {
                                             $admin = " (OX)";
                                          } else {
                                             $admin = " (EX)";
                                          }
                                           echo "<option value=\"$tutor\"";
                                           if ($usrkey == $cat_ref) {
                                             echo "selected='selected'";
                                           }
                                           echo ">$tutorname $thisadmintype</option>";
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
                                    <input class="form-control" type="text" id="syslink" name="syslink">
                                 </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label class="col-form-label" for="year">Cohort Year</label>
                                        <select class="custom-select custom-select mb-3" id="year" name="year" required>
                                          <?php
                                          $startyear = date("Y");
                                          $lastyear = $startyear - 2;
                                          $endyear = $lastyear + 5;
                                          for ($x = $lastyear; $x <= $endyear; $x++) {
                                             echo "<option value=\"$x\"";
                                              if ($x == $startyear) {
                                                echo "selected='selected'";
                                              }
                                              echo ">$x</option>";
                                          }
                                          ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                          <div class="form-group">
                            <label class="col-form-label">Permitted to use Tables:</label>
                            <div class="col-lg-12">
                              <?PHP
                              // Loop through tables 
                              $loopstmt = $mysqli->prepare("SELECT tbid, tab_name, isvis FROM tabs_tbl");
                              $loopstmt->execute();
                              $loopstmt->store_result();
                              $loopstmt->bind_result($tbid, $tab_name, $isvis);
                              while ($loopstmt->fetch()) {
                                 $selected = '';
                                 if ($isvis == 1) {
                                    $selected = 'checked';
                                 }
                                echo "<label class=\"checkbox-inline\"><input name=\"q$tbid\" type=\"checkbox\" $selected value=\"$tbid\"/> $tab_name</label><br>\r";
                              }
                              $loopstmt->close();
                              ?>
                            </div>
                          </div>

                           
                        </div>
                        <div class="card-footer">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="float-right"><button class="btn btn-info" type="submit">Add</button></div>
                        </div>
                     </div><!-- END card-->
                  </form>
               </div>
            </div>
            <?php
            }
            ?>
         </div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
         "lengthMenu": [[ 10, 25, 50, 75, 100, 250, -1 ], [10, 25, 50, 75, 100, 250, "All"] ],
        "pageLength": 250
      });
     $('.summernote').summernote({
        tabsize: 2,
        height: 160,
        spellCheck: true,
        dialogsInBody: true,
        cleaner:{
              action: 'both', // both|button|paste 'button' only cleans via toolbar button, 'paste' only clean when pasting content, both does both options.
              newline: '<br>', // Summernote's default is to use '<p><br></p>'
              notStyle: 'position:absolute;top:0;left:0;right:0', // Position of Notification
              icon: '<i class="note-icon">[Your Button]</i>',
              keepHtml: true, // Remove all Html formats
              keepOnlyTags: ['<p>', '<br>', '<ul>', '<li>', '<b>', '<strong>','<i>', '<a>'], // If keepHtml is true, remove all tags except these
              keepClasses: false, // Remove Classes
              badTags: ['style', 'script', 'applet', 'embed', 'noframes', 'noscript', 'html'], // Remove full tags with contents
              badAttributes: ['style', 'start'], // Remove attributes from remaining tags
              limitChars: false, // 0/false|# 0/false disables option
              limitDisplay: 'both', // text|html|both
              limitStop: false // true/false
        },
        toolbar: [
           ['style', ['style']],
           ['font', ['bold', 'underline', 'superscript', 'subscript']],
           ['color', ['color']],
           ['para', ['ul', 'ol', 'paragraph']],
           ['table', ['table']],
           ['insert', ['link', 'picture', 'video']],
           ['view', ['codeview', 'help']],
         ]
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
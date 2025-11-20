<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Get session variables
$usrkey = isset($_SESSION['usrkey']) ? $_SESSION['usrkey'] : '';

// Ensure today variable is defined
if (!isset($today)) {
    $today = date('Ymd');
}

// Ensure database connection is available
if (!isset($pdo) || !$pdo) {
    die("Database connection not available");
}

// Ensure adminname variable is defined
if (!isset($adminname)) {
    $adminname = 'Admin';
}

// Ensure all required variables are defined with proper defaults
if (!isset($value0)) {
    $value0 = 0;
}
if (!isset($value1)) {
    $value1 = 1;
}

// Get total trainee count for display (before pagination)
$total_count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trainee_tbl");
$total_count_stmt->execute();
$total_trainees = $total_count_stmt->fetchColumn();
$total_count_stmt->closeCursor();

$pagetitle = "Trainee Clinical Psychologists";

// Set variables needed by adminjs.php
setAdminVars(2); // Trainees section
$subtitle = "Trainees";
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
// page actions
$done = isset($_POST['done']) ? $_POST['done'] : '';
$newadmin = isset($_POST['newadmin']) ? $_POST['newadmin'] : '';
$which = isset($_GET['which']) ? $_GET['which'] : 0; # trainkey 32 char
$del = isset($_GET['del']) ? $_GET['del'] : '';
$delalert = '';
if ($del == "del" && ($admintype == 'AT' || $admintype == 'DV')) {
  // wipe data and delete trainee

   // which tables they see
   $stmt = $pdo->prepare("DELETE FROM trainee_tab_link WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();

   // their recorded data
   $stmt = $pdo->prepare("DELETE FROM trainee_log WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();

   // their attendance log
   $stmt = $pdo->prepare("DELETE FROM timesheet WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();

   // any subset
   $stmt = $pdo->prepare("DELETE FROM subset_link_tbl WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();

   // any report certificate
   $stmt = $pdo->prepare("DELETE FROM trainee_report_ok WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();

  // delete the trainee (PostgreSQL doesn't support LIMIT in DELETE)
  $stmt = $pdo->prepare("DELETE FROM trainee_tbl WHERE trainkey = ?");
   $stmt->execute([$which]); 
   if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Trainee's Records Deleted</strong></div></div></div>";
   }
   $stmt->closeCursor();

}
if ($del == "wipe" && ($admintype == 'AT' || $admintype == 'DV')) {
   // we're just wiping their data

   // their recorded data
   $stmt = $pdo->prepare("DELETE FROM trainee_log WHERE trainkey = ?");
   $stmt->execute([$which]); 
   if ($stmt->rowCount() > 0) {
    // show message when deleting, not refreshing
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Trainee Data Wiped</strong></div></div></div>";
   }
   $stmt->closeCursor();

   // their attendance log
   $stmt = $pdo->prepare("DELETE FROM timesheet WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();

   // any report certificate
   $stmt = $pdo->prepare("DELETE FROM trainee_report_ok WHERE trainkey = ?");
   $stmt->execute([$which]); 
   $stmt->closeCursor();
}

if ($newadmin == 'newadmin') {
  // Validate and sanitize all form inputs with proper defaults
  $name = isset($_POST['name']) && !empty($_POST['name']) ? trim($_POST['name']) : '';
  $email = isset($_POST['email']) && !empty($_POST['email']) ? trim($_POST['email']) : '';
  $uid = isset($_POST['uid']) && is_numeric($_POST['uid']) ? (int)$_POST['uid'] : 0;
  $supervisor = isset($_POST['supervisor']) && !empty($_POST['supervisor']) ? trim($_POST['supervisor']) : '';
  $supervisor2 = isset($_POST['supervisor2']) && !empty($_POST['supervisor2']) ? trim($_POST['supervisor2']) : '';
  $supervisor3 = isset($_POST['supervisor3']) && !empty($_POST['supervisor3']) ? trim($_POST['supervisor3']) : '';
  $tutor = isset($_POST['tutor']) && !empty($_POST['tutor']) ? trim($_POST['tutor']) : '';
  $syslink = isset($_POST['syslink']) && !empty($_POST['syslink']) ? trim($_POST['syslink']) : '';
  $year = isset($_POST['year']) && !empty($_POST['year']) ? trim($_POST['year']) : '';
  
  // Validate required fields
  if (empty($name) || empty($email) || empty($uid)) {
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Required fields cannot be empty</strong></div></div></div>";
  } else {
  // create a user key and password
    $txtpw = substr(md5(rand()), 0, 8); # a temp password
    // Create a random salt
    $salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
    $password = hash('sha512', $txtpw.$salt);
    
    $numids = 1;
    while (!$numids == 0) {
      // make 32 digit hex string
      $trainkey = substr(md5(rand()), 0, 32);   
      $stmt = $pdo->prepare("SELECT tid FROM trainee_tbl WHERE trainkey = ? LIMIT 1");
      $stmt->execute([$trainkey]);
      $tid = $stmt->fetchColumn();
      $numids = $stmt->rowCount();
      $stmt->closeCursor();
    }

  // Set meaningful values for all required columns
  $name = $name ?: 'Unknown';
  $email = $email ?: '';
  $uid = $uid ?: 0;
  $supervisor = $supervisor ?: '';
  $supervisor2 = $supervisor2 ?: '';
  $supervisor3 = $supervisor3 ?: '';
  $tutor = $tutor ?: '';
  $syslink = $syslink ?: '';
  $year = $year ?: date('Y'); // Default to current year
  $usrkey = $usrkey ?: '';
  $today = $today ?: date('Ymd');
  
  // Set meaningful defaults for database columns
  $tandc = 0; // Terms and conditions - default to 0 (not accepted)
  $last_used = $today; // Set last_used to today's date
  $date_added = $today; // Set date_added to today's date
  $date_modified = $today; // Set date_modified to today's date
  
  // Debug: Log the values being inserted (remove in production)
  error_log("Trainee insert values - name: '$name', email: '$email', uid: $uid, supervisor: '$supervisor', usrkey: '$usrkey', today: '$today', tandc: $tandc, last_used: $last_used");

  // write new record
  $insert_stmt = $pdo->prepare("INSERT INTO trainee_tbl (name, email, uid, supervisor, supervisor2, supervisor3, syslink, year, trainkey, txtpw, password, salt, who_by, date_added, date_modified, last_used, tandc, tutor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING tid");
  
  if (!$insert_stmt) {
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Failed to prepare statement: " . $pdo->errorInfo()[2] . "</strong></div></div></div>";
  } else {
    if (!$insert_stmt->execute([$name, $email, $uid, $supervisor, $supervisor2, $supervisor3, $syslink, $year, $trainkey, $txtpw, $password, $salt, $usrkey, $date_added, $date_modified, $last_used, $tandc, $tutor])) {
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Error: Failed to insert trainee: " . $insert_stmt->errorInfo()[2] . "</strong></div></div></div>";
    } else {
      $newid = $insert_stmt->fetchColumn();
      $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-success\" role=\"alert\"><strong>Trainee added successfully!</strong></div></div></div>";
    }
    $insert_stmt->closeCursor();
  }

  // create table links
  $tagstmt = $pdo->prepare("SELECT tbid FROM tabs_tbl");
  $tagstmt->execute();
  while ($row = $tagstmt->fetch(PDO::FETCH_ASSOC)){
    $tbid = $row['tbid'];
    $posmarker = 'q'.$tbid;
    $clicked = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
    if ($clicked == $tbid)  {
      // if checkbox has same value add to db
      $insert_stmt = $pdo->prepare("INSERT INTO trainee_tab_link (trainkey, tbid) VALUES (?, ?)");
      $insert_stmt->execute([$trainkey, $tbid]);
      $insert_stmt->closeCursor();
    }
  }
  $tagstmt->closeCursor();
  
  } // Close the validation if statement
  
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
            <div class="content-header" id="report">
               <div class="content-title"><?php echo $pagetitle ?> <a href="#newform" class="btn btn-sm btn-info ml-5">Add New</a><small><?php echo $subtitle ?></small></div>
            </div>
            <?php echo $delalert ?>
            <div class="row mb-3">
               <div class="col text-right">
                  <small class="text-muted">
                     <?php echo $total_trainees; ?> total trainees
                  </small>
               </div>
            </div>

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
// Search and pagination setup
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build search conditions
$search_condition = '';
$search_params = [];

if ($search_term !== '') {
    $search_condition = "
        WHERE (
            t.name ILIKE :search
            OR t.email ILIKE :search
            OR CAST(t.uid AS TEXT) ILIKE :search
            OR CAST(t.year AS TEXT) ILIKE :search
            OR t.syslink ILIKE :search
            OR t.trainkey ILIKE :search
            OR EXISTS (
                SELECT 1
                FROM uni_tbl u
                WHERE u.uid = t.uid
                AND u.university ILIKE :search
            )
            OR EXISTS (
                SELECT 1
                FROM who_there wt
                WHERE wt.realname ILIKE :search
                AND wt.usrkey IN (t.supervisor, t.supervisor2, t.supervisor3, t.tutor)
            )
        )
    ";
    $like_value = "%{$search_term}%";
    $search_params[':search'] = $like_value;
}

// Get filtered count for pagination with search
$count_query = "SELECT COUNT(*) as total FROM trainee_tbl t $search_condition";
$count_stmt = $pdo->prepare($count_query);

if (!empty($search_params)) {
    foreach ($search_params as $param => $value) {
        $count_stmt->bindValue($param, $value, PDO::PARAM_STR);
    }
}
$count_stmt->execute();
$filtered_trainees = $count_stmt->fetchColumn();
$count_stmt->closeCursor();

$total_pages = ceil($filtered_trainees / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';

// Modified query with pagination and search
$query = "
    SELECT
        t.trainkey,
        t.name,
        t.uid,
        t.year,
        t.supervisor,
        t.supervisor2,
        t.supervisor3,
        t.tutor,
        t.txtpw,
        t.email
    FROM trainee_tbl t
    $search_condition
    ORDER BY t.name
    LIMIT :limit OFFSET :offset
";
$tableset = $pdo->prepare($query);

if (!empty($search_params)) {
    foreach ($search_params as $param => $value) {
        $tableset->bindValue($param, $value, PDO::PARAM_STR);
    }
}
$tableset->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);
$tableset->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$tableset->execute();
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
    $trainkey = $row['trainkey'];
    $name = $row['name'];
    $uid = $row['uid'];
    $year = $row['year'];
    $supervisor = $row['supervisor'];
    $supervisor2 = $row['supervisor2'];
    $supervisor3 = $row['supervisor3'];
    $tutor = $row['tutor'];
    $txtpw = $row['txtpw'];
    $email = $row['email'];
   // get course

   $stmt = $pdo->prepare("SELECT university FROM uni_tbl WHERE uid = ?");
   $stmt->execute([$uid]);
   $university = $stmt->fetchColumn();
   $stmt->closeCursor();
   // get supervisors
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$supervisor]);
   $supername = $stmt->fetchColumn();
   $stmt->closeCursor();
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$supervisor2]);
   $supername2 = $stmt->fetchColumn();
   $stmt->closeCursor();
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$supervisor3]);
   $supername3 = $stmt->fetchColumn();
   $stmt->closeCursor();
   // get tutor
   $stmt = $pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$tutor]);
   $tutorname = $stmt->fetchColumn();
   $stmt->closeCursor();
   // how many attendance days this year
   $vids = $pdo->prepare("SELECT /*+ USE_INDEX(timesheet, idx_timesheet_trainkey_taskdate) */ tsid FROM timesheet WHERE trainkey = ? AND taskdate >= ? AND taskdate <= ?");
   $vids->execute([$trainkey, $valueyearstart, $valueyearend]);
   $numtasks = $vids->rowCount();
   $vids->closeCursor();

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
$numrows = $tableset->rowCount();
$tableset->closeCursor();
?>
                           </tbody>
                        </table>
                        
                        <?php if ($filtered_trainees == 0): ?>
                           <div class="text-center py-4">
                              <p class="text-muted">
                                 <?php if (!empty($search_term)): ?>
                                    No trainees found matching "<?php echo htmlspecialchars($search_term); ?>"
                                 <?php else: ?>
                                    No trainees found
                                 <?php endif; ?>
                              </p>
                           </div>
                        <?php endif; ?>
</div>
            </div><!-- end table col -->
            </div><!-- end table row -->
            
            <!-- Pagination Controls -->
            <?php if ($filtered_trainees > 0 && $total_pages > 1): ?>
            <div class="row">
               <div class="col-12">
                  <nav aria-label="Trainee pagination">
                     <ul class="pagination justify-content-center">
                        <?php 
                        // Build query string for pagination links
                        $query_params = [];
                        if (!empty($search_term)) {
                            $query_params['search'] = $search_term;
                        }
                        ?>
                        
                        <?php if ($current_page > 1): ?>
                           <li class="page-item">
                              <?php $query_params['page'] = $current_page - 1; ?>
                              <a class="page-link" href="?<?php echo http_build_query($query_params); ?>#report" aria-label="Previous">
                                 <span aria-hidden="true">&laquo;</span>
                              </a>
                           </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                           <li class="page-item">
                              <?php $query_params['page'] = 1; ?>
                              <a class="page-link" href="?<?php echo http_build_query($query_params); ?>#report">1</a>
                           </li>
                           <?php if ($start_page > 2): ?>
                              <li class="page-item disabled"><span class="page-link">...</span></li>
                           <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                           <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                              <?php $query_params['page'] = $i; ?>
                              <a class="page-link" href="?<?php echo http_build_query($query_params); ?>#report"><?php echo $i; ?></a>
                           </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                           <?php if ($end_page < $total_pages - 1): ?>
                              <li class="page-item disabled"><span class="page-link">...</span></li>
                           <?php endif; ?>
                           <li class="page-item">
                              <?php $query_params['page'] = $total_pages; ?>
                              <a class="page-link" href="?<?php echo http_build_query($query_params); ?>#report"><?php echo $total_pages; ?></a>
                           </li>
                        <?php endif; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                           <li class="page-item">
                              <?php $query_params['page'] = $current_page + 1; ?>
                              <a class="page-link" href="?<?php echo http_build_query($query_params); ?>#report" aria-label="Next">
                                 <span aria-hidden="true">&raquo;</span>
                              </a>
                           </li>
                        <?php endif; ?>
                     </ul>
                  </nav>
                  
                  <div class="text-center mt-2">
                     <small class="text-muted">
                        <?php if (!empty($search_term)): ?>
                           Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $filtered_trainees); ?> of <?php echo $filtered_trainees; ?> matching trainees (<?php echo $total_trainees; ?> total)
                        <?php else: ?>
                           Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $filtered_trainees); ?> of <?php echo $filtered_trainees; ?> trainees
                        <?php endif; ?>
                     </small>
</div>
            </div>
            <?php endif; ?>
            <?php
           if ($admintype == 'AT' || $admintype == 'DV') {
           ?>
           <div class="row my-5" id="newform">
              <div class="col-12">
                  <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" data-parsley-validate="" novalidate="">
                    <!-- START card-->
                    <div class="card border-info shadow-sm">
                        <div class="card-header bg-info text-white">
                           <div class="card-title mb-0">Add new Trainee</div>
                        </div>

                        <div class="card-body p-4">
                           <div class="alert alert-info mb-4">
                              <strong>Note:</strong> Fields marked with <span class="text-danger">*</span> are required.
                           </div>
                           
                           <!-- Basic Information Section -->
                           <div class="form-section mb-4">
                              <h5 class="text-primary mb-3">Basic Information</h5>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="name">Trainee Name <span class="text-danger">*</span></label>
                                       <input class="form-control" type="text" id="name" name="name" required 
                                              placeholder="Enter trainee's full name">
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="email">Trainee Email <span class="text-danger">*</span></label>
                                       <input class="form-control" type="email" id="email" name="email" required 
                                              placeholder="Enter trainee's email address">
                                    </div>
                                 </div>
                              </div>
                           </div>

                           <hr class="my-4">
                           
                           <!-- Course Information Section -->
                           <div class="form-section mb-4">
                              <h5 class="text-success mb-3">Course Information</h5>
                              <div class="form-group">
                                 <label class="form-label font-weight-bold" for="uid">University Course <span class="text-danger">*</span></label>
                                 <select class="form-control" id="uid" name="uid" required>
                                    <option selected="selected" value="0">Select a university course...</option>
                                    <?php
                                    // list the groups, grouping as there are repeats
                                    $cat_ref = '';
                                    $tableset = $pdo->prepare("SELECT uid, university FROM uni_tbl ORDER BY university");
                                    $tableset->execute();
                                    while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                      $uid = $row['uid'];
                                      $university = $row['university'];
                                      echo "<option value=\"$uid\"";
                                      if ($uid == $cat_ref) {
                                        echo "selected='selected'";
                                      }
                                      echo ">$university</option>";
                                    }
                                    $tableset->closeCursor();
                                    ?>
                                 </select>
                              </div>
                           </div>

                           <hr class="my-4">
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
                           <!-- Supervision & Support Section -->
                           <div class="form-section mb-4">
                              <h5 class="text-warning mb-3">Supervision & Support</h5>
                              <div class="row">
                                 <div class="col-12 col-md-6 form-group">
                                    <label class="form-label font-weight-bold" for="supervisor">Primary Supervisor</label>
                                    <select class="form-control" id="supervisor" name="supervisor">
                                       <option selected="selected" value="0">Select primary supervisor...</option>
                                       <?php
                                       // list Supervisors Only (& full admin)
                                       $cat_ref = '';
                                       $tableset = $pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE (admintype = ? OR admintype = ? OR admintype = ?) ORDER BY realname");
                                       $tableset->execute([$adminSO, $adminSE, $adminAT]);
                                       while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                         $supervisor = $row['usrkey'];
                                         $realname = $row['realname'];
                                         $thisadmintype = $row['admintype'];
                                        if ($thisadmintype == 'SO') {
                                           $admin = " (OX)";
                                        } else {
                                           $admin = " (EX)";
                                        }
                                        echo "<option value=\"$supervisor\"";
                                         if ($usrkey == $cat_ref) {
                                           echo "selected='selected'";
                                         }
                                        echo ">$realname $admin</option>";
                                       }
                                       $tableset->closeCursor();
                                       ?>
                                    </select>
                                 </div>
                                 <div class="col-12 col-md-6 form-group">
                                    <label class="form-label font-weight-bold" for="supervisor2">Secondary Supervisor</label>
                                    <select class="form-control" id="supervisor2" name="supervisor2">
                                       <option selected="selected" value="0">Select secondary supervisor...</option>
                                       <?php
                                       // list Supervisors Only (& full admin)
                                       $cat_ref = '';
                                       $tableset = $pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE (admintype = ? OR admintype = ? OR admintype = ?) ORDER BY realname");
                                       $tableset->execute([$adminSO, $adminSE, $adminAT]);
                                       while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                         $supervisor = $row['usrkey'];
                                         $realname = $row['realname'];
                                         $thisadmintype = $row['admintype'];
                                        if ($thisadmintype == 'SO') {
                                           $admin = " (OX)";
                                        } else {
                                           $admin = " (EX)";
                                        }
                                        echo "<option value=\"$supervisor\"";
                                         if ($usrkey == $cat_ref) {
                                           echo "selected='selected'";
                                         }
                                        echo ">$realname $admin</option>";
                                       }
                                       $tableset->closeCursor();
                                       ?>
                                    </select>
                                </div>
                              </div>
                              <div class="row">
                                 <div class="col-12 col-md-6 form-group">
                                    <label class="form-label font-weight-bold" for="supervisor3">Tertiary Supervisor</label>
                                    <select class="form-control" id="supervisor3" name="supervisor3">
                                       <option selected="selected" value="0">Select tertiary supervisor...</option>
                                       <?php
                                       // list Supervisors Only (& full admin)
                                       $cat_ref = '';
                                       $tableset = $pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE (admintype = ? OR admintype = ? OR admintype = ?) ORDER BY realname");
                                       $tableset->execute([$adminSO, $adminSE, $adminAT]);
                                       while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                         $supervisor = $row['usrkey'];
                                         $realname = $row['realname'];
                                         $thisadmintype = $row['admintype'];
                                        if ($thisadmintype == 'SO') {
                                           $admin = " (OX)";
                                        } else {
                                           $admin = " (EX)";
                                        }
                                        echo "<option value=\"$supervisor\"";
                                         if ($usrkey == $cat_ref) {
                                           echo "selected='selected'";
                                         }
                                        echo ">$realname $admin</option>";
                                       }
                                       $tableset->closeCursor();
                                       ?>
                                    </select>
                                 </div>
                                 <div class="col-12 col-md-6 form-group">
                                    <label class="form-label font-weight-bold" for="tutor">Course Tutor</label>
                                    <select class="form-control" id="tutor" name="tutor">
                                       <option selected="selected" value="0">Select course tutor...</option>
                                       <?php
                                       // list Tutors Only (& full admin)
                                       $cat_ref = '';
                                       $tableset = $pdo->prepare("SELECT usrkey, realname, admintype FROM who_there WHERE admintype = ? OR admintype = ? OR admintype = ? ORDER BY realname");
                                       $tableset->execute([$tutorAO, $tutorAE, $adminAT]);
                                       while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                                         $tutor = $row['usrkey'];
                                         $tutorname = $row['realname'];
                                         $thisadmintype = $row['admintype'];
                                        if ($thisadmintype == 'AO') {
                                           $admin = " (OX)";
                                        } else {
                                           $admin = " (EX)";
                                        }
                                        echo "<option value=\"$tutor\"";
                                         if ($usrkey == $cat_ref) {
                                           echo "selected='selected'";
                                         }
                                        echo ">$tutorname $admin</option>";
                                       }
                                       $tableset->closeCursor();
                                       ?>
                                    </select>
                                </div>
                              </div>
                           </div>

                           <hr class="my-4">
                             
                           <!-- Additional Information Section -->
                           <div class="form-section mb-4">
                              <h5 class="text-info mb-3">Additional Information</h5>
                              <div class="row">
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="syslink">Employee Number</label>
                                       <input class="form-control" type="text" id="syslink" name="syslink" 
                                              placeholder="Enter employee number (optional)">
                                    </div>
                                 </div>
                                 <div class="col-12 col-md-6">
                                    <div class="form-group">
                                       <label class="form-label font-weight-bold" for="year">Cohort Year <span class="text-danger">*</span></label>
                                       <select class="form-control" id="year" name="year" required>
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
                           </div>

                           <hr class="my-4">
                           <!-- Permissions Section -->
                           <div class="form-section mb-4">
                              <h5 class="text-secondary mb-3">Sheet Permissions</h5>
                              <div class="form-group">
                                 <label class="form-label font-weight-bold">Permitted to use Sheets:</label>
                                 <div class="row">
                                    <?PHP
                                    // Loop through tables 
                                    $loopstmt = $pdo->prepare("SELECT tbid, tab_name, isvis FROM tabs_tbl ORDER BY tab_name");
                                    $loopstmt->execute();
                                    $colCount = 0;
                                    while ($row = $loopstmt->fetch(PDO::FETCH_ASSOC)) {
                                      $tbid = $row['tbid'];
                                      $tab_name = $row['tab_name'];
                                      $isvis = $row['isvis'];
                                      $selected = '';
                                      if ($isvis == 1) {
                                         $selected = 'checked';
                                      }
                                      
                                      if ($colCount % 3 == 0) {
                                         echo '<div class="col-12 col-md-4">';
                                      }
                                      
                                      echo "<div class=\"form-check mb-2\">";
                                      echo "<input class=\"form-check-input\" name=\"q$tbid\" type=\"checkbox\" $selected value=\"$tbid\" id=\"sheet_$tbid\">";
                                      echo "<label class=\"form-check-label\" for=\"sheet_$tbid\">$tab_name</label>";
                                      echo "</div>";
                                      
                                      $colCount++;
                                      if ($colCount % 3 == 0) {
                                         echo '</div>';
                                      }
                                    }
                                    
                                    // Close the last column if needed
                                    if ($colCount % 3 != 0) {
                                       echo '</div>';
                                    }
                                    
                                    $loopstmt->closeCursor();
                                    ?>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="card-footer bg-light border-top">
                           <input type="hidden" name="newadmin" value="newadmin">
                           <div class="d-flex justify-content-between align-items-center">
                              <small class="text-muted">
                                 All fields marked with <span class="text-danger">*</span> are required
                              </small>
                              <button class="btn btn-info btn-lg px-4" type="submit">
                                 Add Trainee
                              </button>
                           </div>
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
     var traineeDataTable = $('#maintable').DataTable( {
         "lengthMenu": [[ 10, 25, 50, 75, 100, 250, -1 ], [10, 25, 50, 75, 100, 250, "All"] ],
        "pageLength": 250
      });
     
     var dataTableSearchInput = $('#maintable_filter input[type="search"]').first();
     var activeSearchTerm = <?php echo json_encode($search_term); ?>;
     var searchDebounceTimer = null;
     var searchDebounceDelayMs = 400;
     
     if (dataTableSearchInput.length) {
        // Remove DataTables' default filtering handlers so we can control behavior
        dataTableSearchInput.off('.DT');
        dataTableSearchInput.val(activeSearchTerm);
        console.log('[TraineeSearch] Initialised search input with value:', activeSearchTerm);
        
        var triggerServerSearch = function(term) {
           var trimmedTerm = term.trim();
           var currentUrl = new URL(window.location.href);
           
           if (trimmedTerm.length) {
              currentUrl.searchParams.set('search', trimmedTerm);
              currentUrl.searchParams.set('page', '1');
              console.log('[TraineeSearch] Applying search term to URL:', trimmedTerm);
           } else {
              currentUrl.searchParams.delete('search');
              currentUrl.searchParams.delete('page');
              console.log('[TraineeSearch] Clearing search term from URL');
           }
           
           currentUrl.hash = 'report';
           window.location.href = currentUrl.toString();
        };
        
        dataTableSearchInput.on('input', function() {
           var value = $(this).val();
           console.log('[TraineeSearch] Search input changed:', value);
           clearTimeout(searchDebounceTimer);
           searchDebounceTimer = setTimeout(function() {
              triggerServerSearch(value);
           }, searchDebounceDelayMs);
        });
        
        dataTableSearchInput.on('keydown', function(event) {
           if (event.key === 'Enter') {
              event.preventDefault();
              clearTimeout(searchDebounceTimer);
              triggerServerSearch($(this).val());
           }
        });
     } else {
        console.warn('[TraineeSearch] DataTables search input not found');
     }
     
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

      // Form validation enhancement
      $('form').on('submit', function(e) {
         var isValid = true;
         var errorMessages = [];

         // Check required fields
         $('input[required], select[required]').each(function() {
            if (!$(this).val()) {
               $(this).addClass('is-invalid');
               isValid = false;
            } else {
               $(this).removeClass('is-invalid');
            }
         });

         // Check email format
         var email = $('#email').val();
         var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
         if (email && !emailRegex.test(email)) {
            errorMessages.push('Please enter a valid email address');
            $('#email').addClass('is-invalid');
            isValid = false;
         }

         if (!isValid) {
            e.preventDefault();
            var alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
               '<strong>Please fix the following errors:</strong><ul class="mb-0 mt-2">';
            errorMessages.forEach(function(msg) {
               alertHtml += '<li>' + msg + '</li>';
            });
            alertHtml += '</ul><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
            
            // Remove existing alerts
            $('.alert-danger').remove();
            // Add new alert at top of form
            $('.card-body').prepend(alertHtml);
            
            // Scroll to top of form
            $('html, body').animate({
               scrollTop: $('#newform').offset().top - 100
            }, 500);
         }
      });

      // Remove validation classes on input
      $('input, select').on('input change', function() {
         $(this).removeClass('is-invalid');
      });

      // Smooth scroll to form when clicking "Add New" button
      $('a[href="#newform"]').on('click', function(e) {
         e.preventDefault();
         $('html, body').animate({
            scrollTop: $('#newform').offset().top - 100
         }, 500);
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